<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\LoginFormAuthenticator;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login')]
    public function login(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        // if user is already logged in, redirect to main page
        if ($this->getUser()) {
            return $this->redirectToRoute('app_mainpage');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();
        $initialView = $request->query->get('view', 'login');

        if (!in_array($initialView, ['login', 'signup'], true)) {
            $initialView = 'login';
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'initial_view' => $initialView,
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route(path: '/signup', name: 'app_register')]
    public function register(): Response
    {
        return $this->redirectToRoute('app_login', ['view' => 'signup']);
    }

    #[Route(path: '/forgot-password', name: 'app_forgot_password')]
    public function forgotPassword(): Response
    {
        return $this->render('security/forgot_password.html.twig');
    }

    #[Route(path: '/login/face-id', name: 'app_login_face_id', methods: ['POST'])]
    public function loginWithFaceId(
        Request $request,
        Connection $connection,
        UserRepository $userRepository,
        UserAuthenticatorInterface $userAuthenticator,
        LoginFormAuthenticator $formAuthenticator
    ): Response {
        if ($this->getUser() instanceof User) {
            return $this->json([
                'success' => true,
                'message' => 'Already logged in.',
                'redirectUrl' => $this->generateUrl('app_mainpage'),
            ]);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['success' => false, 'message' => 'Invalid payload.'], Response::HTTP_BAD_REQUEST);
        }

        $imageData = (string) ($payload['imageData'] ?? '');
        $capturedImage = $this->decodeDataUrlImage($imageData);
        if ($capturedImage === null) {
            return $this->json(['success' => false, 'message' => 'Invalid face image.'], Response::HTTP_BAD_REQUEST);
        }

        if (!function_exists('imagecreatefromstring')) {
            return $this->json(['success' => false, 'message' => 'GD extension is required for face matching.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $userRows = $connection->executeQuery(
            'SELECT id, face_data FROM `user` WHERE face_data IS NOT NULL'
        )->fetchAllAssociative();

        if (!$userRows) {
            return $this->json(['success' => false, 'message' => 'No enrolled face data found.'], Response::HTTP_UNAUTHORIZED);
        }

        $bestSimilarity = 0.0;
        $bestUserId = null;

        foreach ($userRows as $row) {
            $storedFace = $row['face_data'] ?? null;
            if (is_resource($storedFace)) {
                $storedFace = stream_get_contents($storedFace);
            }

            if (!is_string($storedFace) || $storedFace === '') {
                continue;
            }

            $similarity = $this->computeFaceSimilarity($capturedImage, $storedFace);
            if ($similarity > $bestSimilarity) {
                $bestSimilarity = $similarity;
                $bestUserId = isset($row['id']) ? (int) $row['id'] : null;
            }
        }

        // Similarity tuned for same-user webcam captures; tighten if false positives appear.
        if ($bestUserId === null || $bestSimilarity < 0.86) {
            return $this->json(['success' => false, 'message' => 'Face not recognized.'], Response::HTTP_UNAUTHORIZED);
        }

        $matchedUser = $userRepository->find($bestUserId);
        if (!$matchedUser instanceof User) {
            return $this->json(['success' => false, 'message' => 'Matched user not found.'], Response::HTTP_UNAUTHORIZED);
        }

        $userAuthenticator->authenticateUser($matchedUser, $formAuthenticator, $request);

        return $this->json([
            'success' => true,
            'message' => 'Face recognized. Logging in...',
            'redirectUrl' => $this->generateUrl('app_mainpage'),
        ]);
    }

    private function decodeDataUrlImage(string $imageData): ?string
    {
        if ($imageData === '' || !str_starts_with($imageData, 'data:image/')) {
            return null;
        }

        $separator = strpos($imageData, ',');
        if ($separator === false) {
            return null;
        }

        $decoded = base64_decode(substr($imageData, $separator + 1), true);
        if (!is_string($decoded) || $decoded === '') {
            return null;
        }

        return $decoded;
    }

    private function computeFaceSimilarity(string $firstImageBinary, string $secondImageBinary): float
    {
        $firstVector = $this->createGrayVector($firstImageBinary, 40);
        $secondVector = $this->createGrayVector($secondImageBinary, 40);

        if ($firstVector === null || $secondVector === null || count($firstVector) !== count($secondVector)) {
            return 0.0;
        }

        $distanceSum = 0.0;
        $count = count($firstVector);

        for ($index = 0; $index < $count; $index++) {
            $distanceSum += abs($firstVector[$index] - $secondVector[$index]) / 255.0;
        }

        $averageDistance = $count > 0 ? ($distanceSum / $count) : 1.0;
        $similarity = 1.0 - $averageDistance;

        return max(0.0, min(1.0, $similarity));
    }

    private function createGrayVector(string $imageBinary, int $size): ?array
    {
        $source = @imagecreatefromstring($imageBinary);
        if ($source === false) {
            return null;
        }

        $scaled = imagecreatetruecolor($size, $size);
        if ($scaled === false) {
            imagedestroy($source);
            return null;
        }

        imagecopyresampled(
            $scaled,
            $source,
            0,
            0,
            0,
            0,
            $size,
            $size,
            imagesx($source),
            imagesy($source)
        );

        $vector = [];
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                $rgb = imagecolorat($scaled, $x, $y);
                $red = ($rgb >> 16) & 0xFF;
                $green = ($rgb >> 8) & 0xFF;
                $blue = $rgb & 0xFF;

                $gray = (int) round(($red * 0.299) + ($green * 0.587) + ($blue * 0.114));
                $vector[] = $gray;
            }
        }

        imagedestroy($scaled);
        imagedestroy($source);

        return $vector;
    }

    #[Route(path: '/two-factor', name: 'app_two_factor_verify', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function twoFactor(Request $request, Connection $connection): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || $user->getId() === null) {
            return $this->redirectToRoute('app_login');
        }

        if ($request->getSession()->get('two_factor_verified', false) === true) {
            return $this->redirectToRoute('app_mainpage');
        }

        $expiresAtRaw = $connection->fetchOne(
            'SELECT two_factor_expiry FROM `user` WHERE id = ?',
            [(int) $user->getId()],
            [ParameterType::INTEGER]
        );

        $expiresIn = null;
        if (is_string($expiresAtRaw) && $expiresAtRaw !== '') {
            try {
                $expiresAt = new \DateTimeImmutable($expiresAtRaw);
                $expiresIn = max(0, $expiresAt->getTimestamp() - time());
            } catch (\Throwable $e) {
                $expiresIn = null;
            }
        }

        return $this->render('security/two_factor.html.twig', [
            'expires_in' => $expiresIn,
            'user_email' => (string) $user->getEmail(),
        ]);
    }

    #[Route(path: '/two-factor/verify', name: 'app_two_factor_verify_submit', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function verifyTwoFactor(Request $request, Connection $connection): Response
    {
        if (!$this->isCsrfTokenValid('two_factor_verify', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid verification request.');
            return $this->redirectToRoute('app_two_factor_verify');
        }

        $code = preg_replace('/\D+/', '', (string) $request->request->get('code', ''));
        if (!is_string($code) || strlen($code) !== 6) {
            $this->addFlash('error', 'Please enter a valid 6-digit code.');
            return $this->redirectToRoute('app_two_factor_verify');
        }

        $user = $this->getUser();
        if (!$user instanceof User || $user->getId() === null) {
            return $this->redirectToRoute('app_login');
        }

        $row = $connection->executeQuery(
            'SELECT two_factor_code, two_factor_expiry FROM `user` WHERE id = ? LIMIT 1',
            [(int) $user->getId()],
            [ParameterType::INTEGER]
        )->fetchAssociative();

        if (!$row || empty($row['two_factor_code']) || empty($row['two_factor_expiry'])) {
            $this->addFlash('error', 'No verification code found. Please request a new code.');
            return $this->redirectToRoute('app_two_factor_verify');
        }

        $isValidCode = hash_equals((string) $row['two_factor_code'], $code);
        $isNotExpired = false;

        try {
            $expiry = new \DateTimeImmutable((string) $row['two_factor_expiry']);
            $isNotExpired = $expiry >= new \DateTimeImmutable('now');
        } catch (\Throwable $e) {
            $isNotExpired = false;
        }

        if (!$isValidCode || !$isNotExpired) {
            $this->addFlash('error', 'Invalid or expired code.');
            return $this->redirectToRoute('app_two_factor_verify');
        }

        $connection->executeStatement(
            'UPDATE `user` SET two_factor_code = NULL, two_factor_expiry = NULL WHERE id = ?',
            [(int) $user->getId()],
            [ParameterType::INTEGER]
        );

        $request->getSession()->set('two_factor_verified', true);
        $request->getSession()->remove('two_factor_pending_user_id');
        $this->addFlash('success', '2FA verified successfully.');

        return $this->redirectToRoute('app_mainpage');
    }

    #[Route(path: '/two-factor/resend', name: 'app_two_factor_resend', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function resendTwoFactorCode(Request $request, Connection $connection, MailerInterface $mailer): Response
    {
        if (!$this->isCsrfTokenValid('two_factor_resend', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid resend request.');
            return $this->redirectToRoute('app_two_factor_verify');
        }

        $user = $this->getUser();
        if (!$user instanceof User || $user->getId() === null) {
            return $this->redirectToRoute('app_login');
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiry = new \DateTimeImmutable('+10 minutes');

        $connection->executeStatement(
            'UPDATE `user` SET two_factor_code = ?, two_factor_expiry = ? WHERE id = ?',
            [$code, $expiry->format('Y-m-d H:i:s'), (int) $user->getId()],
            [ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER]
        );

        try {
            $email = (new Email())
                ->from((string) ($_ENV['EMAIL_FROM'] ?? $_SERVER['EMAIL_FROM'] ?? 'noreply@rehletna.tn'))
                ->to((string) $user->getEmail())
                ->subject('Your new Rehletna 2FA verification code')
                ->text(
                    "Hello " . (string) ($user->getFullName() ?: $user->getUsername()) . ",\n\n"
                    . "Your new verification code is: {$code}\n"
                    . "This code expires in 10 minutes."
                );

            $mailer->send($email);
            $this->addFlash('success', 'A new verification code has been sent to your email.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Code regenerated, but email could not be sent.');
        }

        return $this->redirectToRoute('app_two_factor_verify');
    }
}