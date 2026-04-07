<?php
// src/Controller/HomeController.php

namespace App\Controller;

use App\Entity\User;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    #[Route('/home', name: 'app_home_home')]
    public function index(Request $request, TokenStorageInterface $tokenStorage): Response
    {
        if ($this->getUser()) {
            $tokenStorage->setToken(null);
            if ($request->hasSession()) {
                $request->getSession()->invalidate();
            }
        }

        return $this->render('home/index.html.twig');
    }

    #[Route('/language/{locale}', name: 'app_set_locale', methods: ['GET', 'POST'])]
    public function setLocale(string $locale, Request $request, Connection $connection): Response
    {
        $normalizedLocale = in_array($locale, ['en', 'fr'], true) ? $locale : 'en';
        $session = $request->getSession();
        $session->set('_locale', $normalizedLocale);
        $session->save();

        $user = $this->getUser();
        if ($user instanceof User) {
            $profile = $this->fetchOrCreateProfileRow($connection, (int) $user->getId());
            if ($profile && isset($profile['id'])) {
                $connection->executeStatement(
                    'UPDATE profile SET language = ? WHERE id = ?',
                    [$normalizedLocale === 'fr' ? 'Francais' : 'English', (int) $profile['id']],
                    [ParameterType::STRING, ParameterType::INTEGER]
                );
            }
        }

        $redirectTo = $request->query->get('redirect') ?: $request->request->get('redirect', '');
        if (empty($redirectTo) || str_starts_with($redirectTo, 'http://') || str_starts_with($redirectTo, 'https://')) {
            $redirectTo = $this->generateUrl('app_mainpage');
        }

        return $this->redirect($redirectTo);
    }

    #[Route('/panel/2fa/toggle', name: 'app_panel_toggle_2fa', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function toggleTwoFactor(Connection $connection, MailerInterface $mailer): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User || $user->getId() === null) {
            return $this->json(['success' => false, 'error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $userId = (int) $user->getId();
        $columnName = $this->resolveTwoFactorColumnName($connection);
        $currentEnabled = (bool) $connection->fetchOne(
            sprintf('SELECT %s FROM `user` WHERE id = ?', $columnName),
            [$userId],
            [ParameterType::INTEGER]
        );

        $nextEnabled = $currentEnabled ? 0 : 1;

        $connection->executeStatement(
            sprintf('UPDATE `user` SET %s = ? WHERE id = ?', $columnName),
            [$nextEnabled, $userId],
            [ParameterType::INTEGER, ParameterType::INTEGER]
        );

        $emailSent = false;
        if ($nextEnabled === 1) {
            try {
                $email = (new Email())
                    ->from((string) ($_ENV['EMAIL_FROM'] ?? $_SERVER['EMAIL_FROM'] ?? 'noreply@rehletna.tn'))
                    ->to((string) $user->getEmail())
                    ->subject('2FA Activated Successfully - Rehletna')
                    ->text(
                        "Hello " . (string) ($user->getFullName() ?: $user->getUsername()) . ",\n\n"
                        . "We confirm that 2FA has been activated successfully on your Rehletna account.\n"
                        . "Security Status: ACTIVE\n\n"
                        . "If you did not make this change, please update your password immediately and contact support."
                    )
                    ->html(sprintf(
                        '<div style="font-family:Segoe UI,Arial,sans-serif;background:#f3f8ff;padding:24px;">'
                        . '<div style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #d6e6f7;border-radius:16px;overflow:hidden;box-shadow:0 10px 28px rgba(10,42,76,0.12);">'
                        . '<div style="background:linear-gradient(135deg,#0f7ab0,#0BA4A1);padding:20px;color:#fff;">'
                        . '<h2 style="margin:0;font-size:24px;">Two-Factor Authentication Activated</h2>'
                        . '<p style="margin:8px 0 0;opacity:.92;">Your account security is now stronger.</p>'
                        . '</div>'
                        . '<div style="padding:22px;color:#1f3f5f;">'
                        . '<p style="margin:0 0 12px;">Hello <strong>%s</strong>,</p>'
                        . '<p style="margin:0 0 12px;line-height:1.6;">We confirm that 2FA has been activated successfully on your Rehletna account.</p>'
                        . '<div style="margin:14px 0;padding:12px 14px;border:1px solid #c7ddf2;border-radius:10px;background:#f7fbff;">'
                        . '<div style="font-weight:700;color:#0f4f79;">Security Status: <span style="color:#11885f;">ACTIVE</span></div>'
                        . '</div>'
                        . '<p style="margin:0;line-height:1.6;">If you did not make this change, please update your password immediately and contact support.</p>'
                        . '</div>'
                        . '</div>'
                        . '</div>',
                        htmlspecialchars((string) ($user->getFullName() ?: $user->getUsername()), ENT_QUOTES)
                    ));

                $mailer->send($email);
                $emailSent = true;
            } catch (\Throwable $e) {
                // Keep toggle successful even if email transport fails.
            }
        }

        return $this->json([
            'success' => true,
            'enabled' => (bool) $nextEnabled,
            'emailSent' => $emailSent,
            'message' => $nextEnabled ? '2FA activated successfully.' : '2FA deactivated successfully.',
        ]);
    }

    #[Route('/panel/2fa/send-credentials-qr', name: 'app_panel_send_credentials_qr', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function sendCredentialsQr(MailerInterface $mailer): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User || $user->getId() === null) {
            return $this->json(['success' => false, 'error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $emailAddress = (string) $user->getEmail();
        $passwordHash = (string) $user->getPassword();
        $displayName = (string) ($user->getFullName() ?: $user->getUsername());

        if ($emailAddress === '' || $passwordHash === '') {
            return $this->json(['success' => false, 'error' => 'Missing credentials payload'], Response::HTTP_BAD_REQUEST);
        }

        $qrPayload = sprintf("email: %s\npassword_hash: %s", $emailAddress, $passwordHash);
        $qrImageUrl = 'https://quickchart.io/qr?size=320&margin=2&text=' . rawurlencode($qrPayload);

        try {
            $message = (new Email())
                ->from((string) ($_ENV['EMAIL_FROM'] ?? $_SERVER['EMAIL_FROM'] ?? 'noreply@rehletna.tn'))
                ->to($emailAddress)
                ->subject('Your Rehletna QR Code (Email + Password Hash)')
                ->text(
                    "Hello " . $displayName . ",\n\n"
                    . "A quick scan helps you sign in faster with less typing.\n\n"
                    . $qrPayload . "\n\n"
                    . "If you did not request this email, please secure your account immediately."
                )
                ->html(sprintf(
                    '<div style="font-family:Segoe UI,Arial,sans-serif;background:linear-gradient(160deg,#eef6ff,#f7fbff);padding:28px 14px;">'
                    . '<div style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #d7e7f7;border-radius:18px;overflow:hidden;box-shadow:0 14px 30px rgba(17,53,86,0.12);">'
                    . '<div style="background:linear-gradient(135deg,#0e5f97,#1a86c8);padding:18px 22px;color:#ffffff;">'
                    . '<div style="font-size:12px;letter-spacing:0.4px;opacity:.9;text-transform:uppercase;">Rehletna Security</div>'
                    . '<h2 style="margin:8px 0 0;font-size:24px;line-height:1.2;">Your Login QR Code</h2>'
                    . '</div>'
                    . '<div style="padding:22px;color:#1f3f5f;">'
                    . '<p style="margin:0 0 12px;font-size:15px;line-height:1.7;">Hello <strong>%s</strong>,</p>'
                    . '<p style="margin:0 0 16px;font-size:14px;line-height:1.7;color:#456b90;">A quick scan helps you sign in faster with less typing.</p>'
                    . '<div style="text-align:center;margin:12px 0 8px;padding:14px;border:1px solid #dce9f6;border-radius:14px;background:#f8fbff;">'
                    . '<img src="%s" alt="Credentials QR Code" width="260" height="260" style="max-width:100%%;border:1px solid #cfe1f3;border-radius:12px;padding:10px;background:#fff;">'
                    . '<div style="margin-top:10px;font-size:12px;color:#54779a;">Scan with your trusted QR app</div>'
                    . '</div>'
                    . '<div style="margin-top:16px;padding:10px 12px;border-radius:10px;background:#fff4f4;border:1px solid #f3d2d2;color:#9b3d3d;font-size:12px;line-height:1.6;">If you did not request this email, change your password immediately.</div>'
                    . '</div>'
                    . '</div>'
                    . '</div>',
                    htmlspecialchars($displayName, ENT_QUOTES),
                    htmlspecialchars($qrImageUrl, ENT_QUOTES)
                ));

            $mailer->send($message);
        } catch (\Throwable $e) {
            return $this->json(['success' => false, 'error' => 'Failed to send QR email'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json([
            'success' => true,
            'message' => 'QR code sent successfully.',
        ]);
    }

    #[Route('/panel/face-id/capture', name: 'app_panel_capture_face_id', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function captureFaceId(Request $request, Connection $connection): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User || $user->getId() === null) {
            return $this->json(['success' => false, 'error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['success' => false, 'error' => 'Invalid payload'], Response::HTTP_BAD_REQUEST);
        }

        $imageDataUrl = (string) ($payload['imageData'] ?? '');
        if ($imageDataUrl === '' || !str_starts_with($imageDataUrl, 'data:image/')) {
            return $this->json(['success' => false, 'error' => 'Missing face image'], Response::HTTP_BAD_REQUEST);
        }

        $separatorPos = strpos($imageDataUrl, ',');
        if ($separatorPos === false) {
            return $this->json(['success' => false, 'error' => 'Invalid image format'], Response::HTTP_BAD_REQUEST);
        }

        $base64Data = substr($imageDataUrl, $separatorPos + 1);
        $binaryImage = base64_decode($base64Data, true);
        if ($binaryImage === false || $binaryImage === '') {
            return $this->json(['success' => false, 'error' => 'Image decode failed'], Response::HTTP_BAD_REQUEST);
        }

        $connection->executeStatement(
            'UPDATE `user` SET face_data = ? WHERE id = ?',
            [$binaryImage, (int) $user->getId()],
            [ParameterType::LARGE_OBJECT, ParameterType::INTEGER]
        );

        return $this->json([
            'success' => true,
            'message' => 'Face data captured successfully.',
        ]);
    }

    #[Route('/panel/profile-summary', name: 'app_panel_profile_summary', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function panelProfileSummary(Connection $connection): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User || $user->getId() === null) {
            return $this->json(['success' => false, 'error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $userId = (int) $user->getId();
        $columnName = $this->resolveTwoFactorColumnName($connection);

        $userRow = $connection->executeQuery(
            sprintf('SELECT id, username, name, last_name, role, %s AS twofa FROM `user` WHERE id = ? LIMIT 1', $columnName),
            [$userId],
            [ParameterType::INTEGER]
        )->fetchAssociative();

        if (!$userRow) {
            return $this->json(['success' => false, 'error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        $profileImage = $connection->executeQuery(
            'SELECT image FROM profile WHERE id_user = ? ORDER BY id DESC LIMIT 1',
            [$userId],
            [ParameterType::INTEGER]
        )->fetchOne();

        $imageData = null;
        if (is_resource($profileImage)) {
            $imageData = stream_get_contents($profileImage) ?: null;
        } elseif (is_string($profileImage)) {
            $imageData = $profileImage;
        }

        $imageUrl = '/images/default_image.png';
        if (!empty($imageData)) {
            $mime = 'image/jpeg';
            if (substr($imageData, 0, 8) === "\x89PNG\r\n\x1a\n") {
                $mime = 'image/png';
            } elseif (substr($imageData, 0, 6) === "GIF87a" || substr($imageData, 0, 6) === "GIF89a") {
                $mime = 'image/gif';
            } elseif (substr($imageData, 0, 2) === "\xFF\xD8") {
                $mime = 'image/jpeg';
            }

            $imageUrl = 'data:' . $mime . ';base64,' . base64_encode($imageData);
        }

        return $this->json([
            'success' => true,
            'imageUrl' => $imageUrl,
            'name' => trim((string) ($userRow['name'] ?? '') . ' ' . (string) ($userRow['last_name'] ?? '')),
            'username' => (string) ($userRow['username'] ?? 'user'),
            'role' => strtoupper((string) ($userRow['role'] ?? 'USER')),
            'twoFactorEnabled' => (bool) ($userRow['twofa'] ?? false),
        ]);
    }

    private function resolveTwoFactorColumnName(Connection $connection): string
    {
        $columns = $connection->executeQuery(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user' AND COLUMN_NAME IN ('two_factor_enabled', 'two_factore_enabled')"
        )->fetchFirstColumn();

        if (in_array('two_factor_enabled', $columns, true)) {
            return 'two_factor_enabled';
        }

        if (in_array('two_factore_enabled', $columns, true)) {
            return 'two_factore_enabled';
        }

        return 'two_factor_enabled';
    }

    #[Route('/mainpage', name: 'app_mainpage')]
    #[IsGranted('ROLE_USER')]
    public function mainpage(Request $request, Connection $connection): Response
    {
        $user = $this->getUser();
        $profile = null;
        $visionAccessibleMode = (bool) $request->getSession()->get('vision_accessible_mode', false);
        $visionTheme = (string) $request->getSession()->get('vision_theme', 'default');

        if ($user) {
            $defaults = [
                'member_premium' => 'standard',
                'language' => 'English',
                'coins' => 0,
                'image' => null,
                'image_url' => '/images/default_image.png',
            ];

            // Load profile by linked user id.
            $result = $connection->executeQuery(
                'SELECT id, image, member_premium, language, coins FROM profile WHERE id_user = ? LIMIT 1',
                [$user->getId()]
            );
            $profile = $result->fetchAssociative();

            // If profile doesn't exist, create it with required defaults.
            if (!$profile) {
                $defaultImagePath = $this->getParameter('kernel.project_dir') . '/public/images/default_image.png';
                $defaultImageBlob = null;

                if (is_file($defaultImagePath) && is_readable($defaultImagePath)) {
                    $defaultImageBlob = file_get_contents($defaultImagePath);
                }

                $connection->executeStatement(
                    'INSERT INTO profile (image, member_premium, language, id_user, coins) VALUES (?, ?, ?, ?, ?)',
                    [
                        $defaultImageBlob,
                        'standard',
                        'English',
                        $user->getId(),
                        0,
                    ],
                    [
                        ParameterType::LARGE_OBJECT,
                        ParameterType::STRING,
                        ParameterType::STRING,
                        ParameterType::INTEGER,
                        ParameterType::INTEGER,
                    ]
                );

                $result = $connection->executeQuery(
                    'SELECT id, image, member_premium, language, coins FROM profile WHERE id_user = ? ORDER BY id DESC LIMIT 1',
                    [$user->getId()]
                );
                $profile = $result->fetchAssociative();
            }

            $profile = $profile ? array_merge($defaults, $profile) : $defaults;

            // Handle BLOB image conversion to base64
            if (!empty($profile['image'])) {
                try {
                    $imageData = $profile['image'];
                    
                    // Handle different blob representations
                    if (is_resource($imageData)) {
                        $imageData = stream_get_contents($imageData);
                    } elseif (is_string($imageData)) {
                        // Already a string, use as-is
                    } else {
                        $imageData = null;
                    }
                    
                    // Only encode if we have valid binary data
                    if ($imageData && strlen($imageData) > 0) {
                        $base64 = base64_encode($imageData);
                        
                        // Detect MIME type from magic bytes
                        $mime = 'image/jpeg'; // default
                        if (substr($imageData, 0, 8) === "\x89PNG\r\n\x1a\n") {
                            $mime = 'image/png';
                        } elseif (substr($imageData, 0, 2) === "\xFF\xD8") {
                            $mime = 'image/jpeg';
                        } elseif (substr($imageData, 0, 6) === "GIF87a" || substr($imageData, 0, 6) === "GIF89a") {
                            $mime = 'image/gif';
                        }
                        
                        $profile['image_url'] = 'data:' . $mime . ';base64,' . $base64;
                    }
                } catch (\Exception $e) {
                    // If conversion fails, use default
                    $profile['image_url'] = '/images/default_image.png';
                }
            }
        }

        return $this->render('home/mainpage.html.twig', [
            'profile' => $profile,
            'visionAccessibleMode' => $visionAccessibleMode,
            'visionTheme' => $visionTheme,
        ]);
    }

    #[Route('/vision-test', name: 'app_vision_test', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function visionTest(Request $request): Response
    {
        $groups = $this->visionTestGroups();

        return $this->render('home/vision_test.html.twig', [
            'visionAccessibleMode' => (bool) $request->getSession()->get('vision_accessible_mode', false),
            'visionTheme' => (string) $request->getSession()->get('vision_theme', 'default'),
            'visionGroups' => $groups,
        ]);
    }

    #[Route('/vision-test/submit', name: 'app_vision_test_submit', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function submitVisionTest(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('vision-test', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid vision test request.');
            return $this->redirectToRoute('app_vision_test');
        }

        $groups = $this->visionTestGroups();
        $themeScores = [];
        $totalCorrect = 0;
        $totalPlates = 0;

        foreach ($groups as $group) {
            $theme = $group['theme'];
            $themeScores[$theme] = 0;

            foreach ($group['plates'] as $plate) {
                $totalPlates++;
                $answer = trim((string) $request->request->get($plate['field'], ''));
                if ($answer === $plate['answer']) {
                    $themeScores[$theme]++;
                    $totalCorrect++;
                }
            }
        }

        arsort($themeScores);
        $selectedTheme = array_key_first($themeScores) ?: 'blue';
        if (!in_array($selectedTheme, ['blue', 'red', 'yellow', 'black', 'white'], true)) {
            $selectedTheme = 'blue';
        }

        // Store temp results in session for results page
        $session = $request->getSession();
        $session->set('vision_temp_results', [
            'selectedTheme' => $selectedTheme,
            'themeScores' => $themeScores,
            'totalCorrect' => $totalCorrect,
            'totalPlates' => $totalPlates,
        ]);

        return $this->redirectToRoute('app_vision_test_results');
    }

    #[Route('/vision-test/results', name: 'app_vision_test_results', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function visionTestResults(Request $request): Response
    {
        $session = $request->getSession();
        $tempResults = $session->get('vision_temp_results');

        if (!$tempResults) {
            return $this->redirectToRoute('app_vision_test');
        }

        $selectedTheme = $tempResults['selectedTheme'];
        $themeScores = $tempResults['themeScores'];
        $totalCorrect = $tempResults['totalCorrect'];
        $totalPlates = $tempResults['totalPlates'];

        $colorProblems = $this->getColorProblemText($selectedTheme, $themeScores);

        return $this->render('home/vision_results.html.twig', [
            'selectedTheme' => $selectedTheme,
            'themeScores' => $themeScores,
            'totalCorrect' => $totalCorrect,
            'totalPlates' => $totalPlates,
            'colorProblem' => $colorProblems,
            'visionTheme' => (string) $session->get('vision_theme', 'default'),
        ]);
    }

    #[Route('/vision-test/apply-theme', name: 'app_vision_test_apply_theme', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function applyVisionTheme(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('apply-theme', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid request.');
            return $this->redirectToRoute('app_vision_test_results');
        }

        $session = $request->getSession();
        $tempResults = $session->get('vision_temp_results');

        if (!$tempResults) {
            return $this->redirectToRoute('app_vision_test');
        }

        $selectedTheme = $tempResults['selectedTheme'];
        $totalCorrect = $tempResults['totalCorrect'];
        $totalPlates = $tempResults['totalPlates'];

        // Apply the theme permanently
        $session->set('vision_theme', $selectedTheme);
        $session->set('vision_accessible_mode', $totalCorrect < $totalPlates);
        $session->remove('vision_temp_results');

        $this->addFlash('success', sprintf(
            '✓ Site theme applied to %s!',
            ucfirst($selectedTheme)
        ));

        return $this->redirectToRoute('app_mainpage');
    }

    #[Route('/vision-test/skip-theme', name: 'app_vision_test_skip_theme', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function skipVisionTheme(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('skip-theme', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid request.');
            return $this->redirectToRoute('app_vision_test_results');
        }

        $session = $request->getSession();
        $session->remove('vision_temp_results');

        $this->addFlash('info', 'Theme not applied. Site colors remain normal.');

        return $this->redirectToRoute('app_mainpage');
    }

    #[Route('/settings', name: 'app_settings')]
    #[IsGranted('ROLE_USER')]
    public function settings(Connection $connection): Response
    {
        $user = $this->getUser();
        $profile = null;

        if ($user instanceof User) {
            $profile = $connection->executeQuery(
                'SELECT id, image, member_premium, language, coins FROM profile WHERE id_user = ? LIMIT 1',
                [$user->getId()],
                [ParameterType::INTEGER]
            )->fetchAssociative();

            if (!$profile || !isset($profile['id'])) {
                $profile = $this->fetchOrCreateProfileRow($connection, (int) $user->getId());
            }

            if (is_array($profile)) {
                $profile['image_url'] = $this->imageToUrl($profile['image'] ?? null);
            }
        }

        return $this->render('settings/index.html.twig', [
            'profile' => $profile,
            'user' => $user,
        ]);
    }

    #[Route('/settings/update', name: 'app_settings_update', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function updateSettings(Request $request, Connection $connection): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('settings-update-' . $user->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid settings request token.');
            return $this->redirectToRoute('app_settings');
        }

        $name = trim((string) $request->request->get('name', ''));
        $lastName = trim((string) $request->request->get('last_name', ''));
        $username = trim((string) $request->request->get('username', ''));
        $email = trim((string) $request->request->get('email', ''));

        if ($name === '' || $lastName === '' || $username === '' || $email === '') {
            $this->addFlash('error', 'All profile fields are required.');
            return $this->redirectToRoute('app_settings');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('error', 'Please enter a valid email address.');
            return $this->redirectToRoute('app_settings');
        }

        $connection->beginTransaction();

        try {
            $connection->executeStatement(
                'UPDATE `user` SET name = ?, last_name = ?, username = ?, email = ? WHERE id = ?',
                [$name, $lastName, $username, $email, (int) $user->getId()],
                [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER]
            );

            $profile = $this->fetchOrCreateProfileRow($connection, (int) $user->getId());
            if ($profile && isset($profile['id'])) {
                $imageFile = $request->files->get('avatar_image');
                if ($imageFile !== null && $imageFile->isValid()) {
                    $imageData = @file_get_contents($imageFile->getPathname());
                    if ($imageData !== false && $imageData !== '') {
                        $connection->executeStatement(
                            'UPDATE profile SET image = ? WHERE id = ?',
                            [$imageData, (int) $profile['id']],
                            [ParameterType::LARGE_OBJECT, ParameterType::INTEGER]
                        );
                    }
                }
            }

            $connection->commit();
            $this->addFlash('success', 'Profile updated successfully.');
        } catch (\Throwable $e) {
            $connection->rollBack();
            $this->addFlash('error', 'Unable to update profile: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_settings');
    }

    #[Route('/load-content', name: 'app_load_content', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function loadContent(Request $request, Connection $connection): Response
    {
        $view = $request->request->get('view');
        
        // Load different views based on selection
        switch ($view) {
            case 'services':
                return $this->render('partials/services.html.twig');
            case 'activities':
                return $this->render('partials/activities.html.twig');
            case 'shop':
                return $this->render('partials/shop.html.twig', [
                    'shop' => $this->buildShopViewData($connection),
                ]);
            case 'ai-guide':
                return $this->render('partials/ai_guide.html.twig');
            case 'offers':
                return $this->render('partials/offers.html.twig');
            default:
                return $this->render('partials/welcome.html.twig');
        }
    }

    #[Route('/collect-coin/status', name: 'app_collect_coin_status', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function collectCoinStatus(Request $request, Connection $connection): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['success' => false], 401);
        }

        $profile = $this->fetchOrCreateProfileRow($connection, (int) $user->getId());
        if (!$profile) {
            return $this->json(['success' => false, 'error' => 'Profile not found'], 404);
        }

        $tier = (string) ($profile['member_premium'] ?? 'standard');
        $nextAllowedAt = (int) $request->getSession()->get('coin_collect_next_ts', 0);
        $remaining = max(0, $nextAllowedAt - time());

        return $this->json([
            'success' => true,
            'coins' => (int) ($profile['coins'] ?? 0),
            'tier' => $tier,
            'reward' => $this->coinRewardForTier($tier),
            'cooldownRemaining' => $remaining,
        ]);
    }

    #[Route('/collect-coin', name: 'app_collect_coin', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function collectCoin(Request $request, Connection $connection): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['success' => false], 401);
        }

        $nextAllowedAt = (int) $request->getSession()->get('coin_collect_next_ts', 0);
        $remaining = max(0, $nextAllowedAt - time());
        if ($remaining > 0) {
            return $this->json([
                'success' => false,
                'error' => 'Cooldown active',
                'cooldownRemaining' => $remaining,
            ], 429);
        }

        $profile = $this->fetchOrCreateProfileRow($connection, (int) $user->getId());
        if (!$profile || !isset($profile['id'])) {
            return $this->json(['success' => false, 'error' => 'Profile not found'], 404);
        }

        $tier = (string) ($profile['member_premium'] ?? 'standard');
        $reward = $this->coinRewardForTier($tier);

        $connection->executeStatement(
            'UPDATE profile SET coins = COALESCE(coins, 0) + ? WHERE id = ?',
            [$reward, (int) $profile['id']],
            [ParameterType::INTEGER, ParameterType::INTEGER]
        );

        $newCoins = (int) $connection->fetchOne(
            'SELECT coins FROM profile WHERE id = ? LIMIT 1',
            [(int) $profile['id']],
            [ParameterType::INTEGER]
        );

        $request->getSession()->set('coin_collect_next_ts', time() + 30);

        return $this->json([
            'success' => true,
            'coins' => $newCoins,
            'awarded' => $reward,
            'tier' => $tier,
            'cooldownRemaining' => 30,
        ]);
    }

    private function fetchOrCreateProfileRow(Connection $connection, int $userId): ?array
    {
        $row = $connection->executeQuery(
            'SELECT id, member_premium, coins FROM profile WHERE id_user = ? ORDER BY id DESC LIMIT 1',
            [$userId],
            [ParameterType::INTEGER]
        )->fetchAssociative();

        if ($row) {
            return $row;
        }

        $connection->executeStatement(
            'INSERT INTO profile (image, member_premium, language, id_user, coins) VALUES (?, ?, ?, ?, ?)',
            [null, 'standard', 'English', $userId, 0],
            [ParameterType::LARGE_OBJECT, ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER, ParameterType::INTEGER]
        );

        return $connection->executeQuery(
            'SELECT id, member_premium, coins FROM profile WHERE id_user = ? ORDER BY id DESC LIMIT 1',
            [$userId],
            [ParameterType::INTEGER]
        )->fetchAssociative() ?: null;
    }

    private function getColorProblemText(string $theme, array $themeScores): array
    {
        $descriptions = [
            'blue' => [
                'title' => '🔵 Blue Perception Focus',
                'description' => 'Your eyes show strongest perception in blue color spectrum. This may indicate sensitivity to blue light or difficulty with blue-red distinction.',
                'recommendation' => 'The blue theme provides better contrast for your visual comfort.',
            ],
            'red' => [
                'title' => '🔴 Red Perception Focus',
                'description' => 'Your eyes show strongest perception in red color spectrum. This may indicate red-green color blindness or reduced red sensitivity.',
                'recommendation' => 'The red theme helps enhance contrast for red-color scenarios.',
            ],
            'yellow' => [
                'title' => '🟡 Yellow Perception Focus',
                'description' => 'Your eyes show strongest perception in yellow color spectrum. This may indicate challenges with blue-yellow distinction.',
                'recommendation' => 'The yellow theme provides enhanced visibility in your optimal color range.',
            ],
            'black' => [
                'title' => '⚫ Dark Tone Perception Focus',
                'description' => 'Your eyes show strongest perception in dark and low-contrast areas. This may indicate light sensitivity or need for better contrast.',
                'recommendation' => 'The dark theme reduces eye strain and improves your comfort.',
            ],
            'white' => [
                'title' => '⚪ Bright Tone Perception Focus',
                'description' => 'Your eyes show strongest perception in bright and high-contrast areas. This may indicate preference for clarity and definition.',
                'recommendation' => 'The bright theme maximizes visual clarity for your needs.',
            ],
        ];

        return $descriptions[$theme] ?? [
            'title' => 'Color Perception Result',
            'description' => 'Your results show a unique color perception pattern.',
            'recommendation' => 'You can apply a custom theme to match your visual needs.',
        ];
    }

    private function visionTestGroups(): array
    {
        return [
            [
                'theme' => 'blue',
                'title' => 'Blue focus check',
                'subtitle' => 'Three plates tuned for blue tones.',
                'plates' => [
                    ['field' => 'blue_1', 'answer' => '12', 'image' => 'plate-12.svg', 'alt' => 'Blue tone plate 1'],
                    ['field' => 'blue_2', 'answer' => '8', 'image' => 'plate-8.svg', 'alt' => 'Blue tone plate 2'],
                    ['field' => 'blue_3', 'answer' => '29', 'image' => 'plate-29.svg', 'alt' => 'Blue tone plate 3'],
                ],
            ],
            [
                'theme' => 'red',
                'title' => 'Red focus check',
                'subtitle' => 'Three plates tuned for red tones.',
                'plates' => [
                    ['field' => 'red_1', 'answer' => '16', 'image' => 'plate-red-1.svg', 'alt' => 'Red tone plate 1'],
                    ['field' => 'red_2', 'answer' => '5', 'image' => 'plate-red-2.svg', 'alt' => 'Red tone plate 2'],
                    ['field' => 'red_3', 'answer' => '42', 'image' => 'plate-red-3.svg', 'alt' => 'Red tone plate 3'],
                ],
            ],
            [
                'theme' => 'yellow',
                'title' => 'Yellow focus check',
                'subtitle' => 'Three plates tuned for yellow tones.',
                'plates' => [
                    ['field' => 'yellow_1', 'answer' => '7', 'image' => 'plate-yellow-1.svg', 'alt' => 'Yellow tone plate 1'],
                    ['field' => 'yellow_2', 'answer' => '14', 'image' => 'plate-yellow-2.svg', 'alt' => 'Yellow tone plate 2'],
                    ['field' => 'yellow_3', 'answer' => '61', 'image' => 'plate-yellow-3.svg', 'alt' => 'Yellow tone plate 3'],
                ],
            ],
            [
                'theme' => 'black',
                'title' => 'Black focus check',
                'subtitle' => 'Three plates tuned for black and dark tones.',
                'plates' => [
                    ['field' => 'black_1', 'answer' => '19', 'image' => 'plate-black-1.svg', 'alt' => 'Black tone plate 1'],
                    ['field' => 'black_2', 'answer' => '2', 'image' => 'plate-black-2.svg', 'alt' => 'Black tone plate 2'],
                    ['field' => 'black_3', 'answer' => '88', 'image' => 'plate-black-3.svg', 'alt' => 'Black tone plate 3'],
                ],
            ],
            [
                'theme' => 'white',
                'title' => 'White focus check',
                'subtitle' => 'Three plates tuned for white and bright tones.',
                'plates' => [
                    ['field' => 'white_1', 'answer' => '3', 'image' => 'plate-white-1.svg', 'alt' => 'White tone plate 1'],
                    ['field' => 'white_2', 'answer' => '10', 'image' => 'plate-white-2.svg', 'alt' => 'White tone plate 2'],
                    ['field' => 'white_3', 'answer' => '27', 'image' => 'plate-white-3.svg', 'alt' => 'White tone plate 3'],
                ],
            ],
        ];
    }

    private function coinRewardForTier(string $tier): int
    {
        $normalized = strtolower(trim($tier));

        if (in_array($normalized, ['vip+', 'vip plus', 'vip_plus'], true)) {
            return 25;
        }

        if ($normalized === 'vip') {
            return 20;
        }

        if (in_array($normalized, ['premium', 'premuim'], true)) {
            return 5;
        }

        return 1;
    }

    #[Route('/premium', name: 'app_premium')]
    #[IsGranted('ROLE_USER')]
    public function premium(Connection $connection): Response
    {
        $user = $this->getUser();
        $profile = null;

        if ($user instanceof User) {
            $profile = $this->fetchOrCreateProfileRow($connection, (int) $user->getId());
        }

        return $this->render('premium/subscription.html.twig', [
            'user' => $user,
            'profile' => $profile,
        ]);
    }

    #[Route('/premium/subscribe', name: 'app_premium_subscribe', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function premiumSubscribe(Request $request, Connection $connection): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            $payload = $request->request->all();
        }

        $offer = strtolower(trim((string) ($payload['offer'] ?? '')));
        $allowedOffers = ['premium', 'vip', 'vip+'];
        if (!in_array($offer, $allowedOffers, true)) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid offer selected.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $cardHolder = trim((string) ($payload['cardHolder'] ?? ''));
        $cardNumber = preg_replace('/\D+/', '', (string) ($payload['cardNumber'] ?? ''));
        $expiry = trim((string) ($payload['expiry'] ?? ''));
        $cvc = preg_replace('/\D+/', '', (string) ($payload['cvc'] ?? ''));

        if ($cardHolder === '' || strlen($cardHolder) < 3) {
            return $this->json([
                'success' => false,
                'message' => 'Card holder name is required.',
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($cardNumber === '' || strlen($cardNumber) < 12 || strlen($cardNumber) > 19) {
            return $this->json([
                'success' => false,
                'message' => 'Card number is invalid.',
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $expiry)) {
            return $this->json([
                'success' => false,
                'message' => 'Expiry must be in MM/YY format.',
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($cvc === '' || (strlen($cvc) !== 3 && strlen($cvc) !== 4)) {
            return $this->json([
                'success' => false,
                'message' => 'CVC is invalid.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $profile = $this->fetchOrCreateProfileRow($connection, (int) $user->getId());
        if (!$profile || !isset($profile['id'])) {
            return $this->json([
                'success' => false,
                'message' => 'Profile not found.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $connection->executeStatement(
            'UPDATE profile SET member_premium = ? WHERE id = ?',
            [$offer, (int) $profile['id']],
            [ParameterType::STRING, ParameterType::INTEGER]
        );

        return $this->json([
            'success' => true,
            'message' => 'Subscription activated successfully.',
            'offer' => $offer,
        ]);
    }

    #[Route('/unread-count', name: 'app_unread_count', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function unreadCount(Connection $connection): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['unreadCount' => 0]);
        }

        $unreadCount = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0',
            [$user->getId()],
            [ParameterType::INTEGER]
        );
        
        return $this->json(['unreadCount' => $unreadCount]);
    }

    #[Route('/chat/messages', name: 'app_chat_messages', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function chatMessages(Connection $connection): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['messages' => []]);
        }

        $userId = (int) $user->getId();
        $conversationId = $this->conversationIdForUser($userId);

        $rows = $connection->executeQuery(
            'SELECT m.id, m.sender_id, m.receiver_id, m.message, m.timestamp, m.is_read,
                          u.username, u.name, u.last_name
             FROM messages m
                      INNER JOIN `user` u ON u.id = m.sender_id
             WHERE m.conversation_id = ?
               AND (m.sender_id = ? OR m.receiver_id = ?)
             ORDER BY m.timestamp ASC, m.id ASC',
            [$conversationId, $userId, $userId],
            [ParameterType::STRING, ParameterType::INTEGER, ParameterType::INTEGER]
        )->fetchAllAssociative();

        $connection->executeStatement(
            'UPDATE messages
             SET is_read = 1
             WHERE conversation_id = ?
               AND receiver_id = ?
               AND is_read = 0',
            [$conversationId, $userId],
            [ParameterType::STRING, ParameterType::INTEGER]
        );

        $messages = array_map(static function (array $row) use ($userId): array {
            $senderId = (int) ($row['sender_id'] ?? 0);
            $senderName = trim((string) (($row['name'] ?? '') . ' ' . ($row['last_name'] ?? '')));
            if ($senderName === '') {
                $senderName = (string) ($row['username'] ?? 'User');
            }

            return [
                'id' => (int) $row['id'],
                'message' => (string) $row['message'],
                'timestamp' => (string) $row['timestamp'],
                'isRead' => (int) $row['is_read'] === 1,
                'senderId' => $senderId,
                'senderName' => $senderName,
                'isMine' => $senderId === $userId,
            ];
        }, $rows);

        return $this->json(['messages' => $messages]);
    }

    #[Route('/chat/send', name: 'app_chat_send', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function chatSend(Request $request, Connection $connection): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $text = trim((string) $request->request->get('message', ''));
        if ($text === '') {
            return $this->json(['success' => false, 'error' => 'Message is empty'], 400);
        }

        $senderId = (int) $user->getId();
        $adminIds = $this->getAdminUserIds($connection);

        if (empty($adminIds)) {
            return $this->json(['success' => false, 'error' => 'No admin available'], 404);
        }

        $conversationId = $this->conversationIdForUser($senderId);
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        foreach ($adminIds as $adminId) {
            $connection->executeStatement(
                'INSERT INTO messages (sender_id, receiver_id, message, timestamp, is_read, conversation_id)
                 VALUES (?, ?, ?, ?, 0, ?)',
                [$senderId, $adminId, $text, $now, $conversationId],
                [
                    ParameterType::INTEGER,
                    ParameterType::INTEGER,
                    ParameterType::STRING,
                    ParameterType::STRING,
                    ParameterType::STRING,
                ]
            );
        }

        return $this->json(['success' => true]);
    }

    #[Route('/dashboard', name: 'app_dashboard')]
    #[IsGranted('ROLE_ADMIN')]
    public function dashboard(Connection $connection): Response
    {
        $adminImageUrl = $this->getCurrentUserProfileImageUrl($connection);

        $totalUsers = (int) $connection->fetchOne('SELECT COUNT(*) FROM `user`');
        $admins = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM `user` WHERE UPPER(role) IN (?, ?)',
            ['ADMIN', 'ROLE_ADMIN'],
            [ParameterType::STRING, ParameterType::STRING]
        );
        $guiders = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM `user` WHERE UPPER(role) IN (?, ?)',
            ['GUIDER', 'ROLE_GUIDE'],
            [ParameterType::STRING, ParameterType::STRING]
        );
        $regularUsers = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM `user` WHERE UPPER(role) IN (?, ?)',
            ['USER', 'ROLE_USER'],
            [ParameterType::STRING, ParameterType::STRING]
        );

        $onlineUsers = $connection->executeQuery(
            'SELECT u.id, u.username, u.email, u.role, u.name, u.last_name, p.image
             FROM `user` u
             LEFT JOIN profile p ON u.id = p.id_user
             WHERE LOWER(COALESCE(u.status, ?)) = ?
             ORDER BY u.id DESC
             LIMIT 8',
            ['', 'online'],
            [ParameterType::STRING, ParameterType::STRING]
        )->fetchAllAssociative();

        $offlineUsers = $connection->executeQuery(
            'SELECT u.id, u.username, u.email, u.role, u.name, u.last_name, p.image
             FROM `user` u
             LEFT JOIN profile p ON u.id = p.id_user
             WHERE LOWER(COALESCE(u.status, ?)) <> ?
             ORDER BY u.id DESC
             LIMIT 8',
            ['', 'online'],
            [ParameterType::STRING, ParameterType::STRING]
        )->fetchAllAssociative();

        $recentUsers = $connection->executeQuery(
            'SELECT u.id, u.username, u.email, u.role, u.name, u.last_name, p.image
             FROM `user` u
             LEFT JOIN profile p ON u.id = p.id_user
             ORDER BY u.id DESC
             LIMIT 6'
        )->fetchAllAssociative();

        // Process images to base64 for all user lists
        $recentUsers = $this->processUserImages($recentUsers);
        $onlineUsers = $this->processUserImages($onlineUsers);
        $offlineUsers = $this->processUserImages($offlineUsers);

        return $this->render('dashboard/index.html.twig', [
            'adminImageUrl' => $adminImageUrl,
            'stats' => [
                'totalUsers' => $totalUsers,
                'admins' => $admins,
                'guiders' => $guiders,
                'regularUsers' => $regularUsers,
                'online' => count($onlineUsers),
                'offline' => max(0, $totalUsers - count($onlineUsers)),
            ],
            'recentUsers' => $recentUsers,
            'onlineUsers' => $onlineUsers,
            'offlineUsers' => $offlineUsers,
        ]);
    }

    /**
     * Helper method to process user images and convert to base64
     */
    private function processUserImages(array $users): array
    {
        foreach ($users as &$user) {
            $user['image_url'] = '/images/default_avatar.png'; // default

            if (!empty($user['image'])) {
                try {
                    $imageData = $user['image'];

                    // Handle different blob representations
                    if (is_resource($imageData)) {
                        $imageData = stream_get_contents($imageData);
                    }

                    // Only encode if we have valid binary data
                    if ($imageData && strlen($imageData) > 0) {
                        $base64 = base64_encode($imageData);

                        // Detect MIME type from magic bytes
                        $mime = 'image/jpeg';
                        if (substr($imageData, 0, 8) === "\x89PNG\r\n\x1a\n") {
                            $mime = 'image/png';
                        } elseif (substr($imageData, 0, 2) === "\xFF\xD8") {
                            $mime = 'image/jpeg';
                        } elseif (substr($imageData, 0, 6) === "GIF87a" || substr($imageData, 0, 6) === "GIF89a") {
                            $mime = 'image/gif';
                        }

                        $user['image_url'] = 'data:' . $mime . ';base64,' . $base64;
                    }
                } catch (\Exception $e) {
                    // If conversion fails, use default
                    $user['image_url'] = '/images/default_avatar.png';
                }
            }
        }

        return $users;
    }

    #[Route('/guide-dashboard', name: 'app_guide_dashboard')]
    #[IsGranted('ROLE_GUIDE')]
    public function guideDashboard(): Response
    {
        return $this->render('guide/dashboard.html.twig');
    }

    #[Route('/users', name: 'app_users', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function users(Request $request, Connection $connection, UserPasswordHasherInterface $passwordHasher): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('create-user', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Invalid create user token.');
                return $this->redirectToRoute('app_dashboard', ['module' => 'users']);
            }

            $firstName = trim((string) $request->request->get('first_name', ''));
            $lastName = trim((string) $request->request->get('last_name', ''));
            $username = trim((string) $request->request->get('username', ''));
            $email = trim((string) $request->request->get('email', ''));
            $role = strtoupper(trim((string) $request->request->get('role', 'USER')));
            $password = (string) $request->request->get('password', '');
            $membershipTier = strtolower(trim((string) $request->request->get('membership_tier', 'standard')));

            $allowedRoles = ['USER', 'GUIDER', 'ADMIN', 'AGENCY'];
            $allowedTiers = ['standard', 'premium', 'vip', 'vip+'];
            $errors = [];

            if (!preg_match('/^[A-Za-z]{3,}$/', $firstName)) {
                $errors[] = 'First name must be at least 3 characters and only letters A-z.';
            }

            if (!preg_match('/^[A-Za-z]{3,}$/', $lastName)) {
                $errors[] = 'Last name must be at least 3 characters and only letters A-z.';
            }

            if ($username === '' || mb_strlen($username) > 15) {
                $errors[] = 'Username is required and must be 15 characters max.';
            }

            if (!str_contains($email, '@') || !str_contains($email, '.') || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Email must contain @ and . and be valid.';
            }

            if (!in_array($role, $allowedRoles, true)) {
                $errors[] = 'Role must be USER, GUIDER, ADMIN, or AGENCY.';
            }

            if (!in_array($membershipTier, $allowedTiers, true)) {
                $errors[] = 'Membership tier must be standard, premium, vip, or vip+.';
            }

            if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $password)) {
                $errors[] = 'Password must be 8+ chars and include a-z, A-Z, and a number.';
            }

            $existingUserByUsername = (int) $connection->fetchOne(
                'SELECT COUNT(*) FROM `user` WHERE username = ?',
                [$username],
                [ParameterType::STRING]
            );

            if ($existingUserByUsername > 0) {
                $errors[] = 'Username already exists.';
            }

            $existingUserByEmail = (int) $connection->fetchOne(
                'SELECT COUNT(*) FROM `user` WHERE email = ?',
                [$email],
                [ParameterType::STRING]
            );

            if ($existingUserByEmail > 0) {
                $errors[] = 'Email already exists.';
            }

            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }

                return $this->redirectToRoute('app_dashboard', ['module' => 'users']);
            }

            $defaultImagePath = $this->getParameter('kernel.project_dir') . '/public/images/default_image.png';
            $defaultImageBlob = null;

            if (is_file($defaultImagePath) && is_readable($defaultImagePath)) {
                $defaultImageBlob = file_get_contents($defaultImagePath);
            }

            $connection->beginTransaction();

            try {
                $userEntity = new User();
                $hashedPassword = $passwordHasher->hashPassword($userEntity, $password);

                $connection->executeStatement(
                    'INSERT INTO `user` (email, role, password, name, last_name, date, username, status, two_factor_enabled)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [
                        $email,
                        $role,
                        $hashedPassword,
                        $firstName,
                        $lastName,
                        (new \DateTimeImmutable())->format('Y-m-d'),
                        $username,
                        'offline',
                        0,
                    ],
                    [
                        ParameterType::STRING,
                        ParameterType::STRING,
                        ParameterType::STRING,
                        ParameterType::STRING,
                        ParameterType::STRING,
                        ParameterType::STRING,
                        ParameterType::STRING,
                        ParameterType::STRING,
                        ParameterType::INTEGER,
                    ]
                );

                $newUserId = (int) $connection->lastInsertId();

                $connection->executeStatement(
                    'INSERT INTO profile (image, member_premium, language, id_user, coins)
                     VALUES (?, ?, ?, ?, ?)',
                    [
                        $defaultImageBlob,
                        $membershipTier,
                        'English',
                        $newUserId,
                        0,
                    ],
                    [
                        ParameterType::LARGE_OBJECT,
                        ParameterType::STRING,
                        ParameterType::STRING,
                        ParameterType::INTEGER,
                        ParameterType::INTEGER,
                    ]
                );

                $connection->commit();
                $this->addFlash('success', 'New user added successfully.');
            } catch (\Throwable $e) {
                $connection->rollBack();
                $this->addFlash('error', 'Could not add user. Please verify data and try again.');
            }

            return $this->redirectToRoute('app_dashboard', ['module' => 'users']);
        }

        // Fetch all users with pagination
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;
        $adminImageUrl = $this->getCurrentUserProfileImageUrl($connection);

        $allUsers = $connection->executeQuery(
            'SELECT u.id, u.username, u.email, u.role, u.name, u.last_name, u.status, p.image
             FROM `user` u
             LEFT JOIN (
                 SELECT p1.*
                 FROM profile p1
                 INNER JOIN (
                     SELECT id_user, MAX(id) AS max_id
                     FROM profile
                     GROUP BY id_user
                 ) p2 ON p1.id = p2.max_id
             ) p ON u.id = p.id_user
             ORDER BY u.id DESC
             LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset
        )->fetchAllAssociative();

        $totalUsers = (int) $connection->fetchOne('SELECT COUNT(*) FROM `user`');

        // Process images for all users
        $allUsers = $this->processUserImages($allUsers);

        return $this->render('admin/users.html.twig', [
            'adminImageUrl' => $adminImageUrl,
            'users' => $allUsers,
            'totalUsers' => $totalUsers,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => ceil($totalUsers / $limit),
        ]);
    }

    #[Route('/users/{id}/edit', name: 'app_user_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function userEdit(int $id, Request $request, Connection $connection): Response
    {
        $adminImageUrl = $this->getCurrentUserProfileImageUrl($connection);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('edit-user-' . $id, (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Invalid edit request token.');
                return $this->redirectToRoute('app_user_edit', ['id' => $id]);
            }

            $connection->executeStatement(
                'UPDATE `user`
                 SET name = ?, last_name = ?, username = ?, email = ?, role = ?, status = ?
                 WHERE id = ?',
                [
                    trim((string) $request->request->get('name', '')),
                    trim((string) $request->request->get('last_name', '')),
                    trim((string) $request->request->get('username', '')),
                    trim((string) $request->request->get('email', '')),
                    strtoupper(trim((string) $request->request->get('role', 'USER'))),
                    trim((string) $request->request->get('status', 'offline')),
                    $id,
                ],
                [
                    ParameterType::STRING,
                    ParameterType::STRING,
                    ParameterType::STRING,
                    ParameterType::STRING,
                    ParameterType::STRING,
                    ParameterType::STRING,
                    ParameterType::INTEGER,
                ]
            );

            $memberPremium = trim((string) $request->request->get('member_premium', 'standard'));
            $language = trim((string) $request->request->get('language', 'English'));

            $profileIds = $connection->executeQuery(
                'SELECT id FROM profile WHERE id_user = ? ORDER BY id DESC',
                [$id],
                [ParameterType::INTEGER]
            )->fetchFirstColumn();

            if (!empty($profileIds)) {
                $mainProfileId = (int) $profileIds[0];

                $connection->executeStatement(
                    'UPDATE profile SET member_premium = ?, language = ? WHERE id = ?',
                    [$memberPremium, $language, $mainProfileId],
                    [ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER]
                );

                if (count($profileIds) > 1) {
                    $duplicateIds = array_map('intval', array_slice($profileIds, 1));
                    $connection->executeStatement(
                        'DELETE FROM profile WHERE id IN (?)',
                        [$duplicateIds],
                        [ArrayParameterType::INTEGER]
                    );
                }
            } else {
                $connection->executeStatement(
                    'INSERT INTO profile (id_user, member_premium, language, coins)
                     VALUES (?, ?, ?, 0)',
                    [$id, $memberPremium, $language],
                    [ParameterType::INTEGER, ParameterType::STRING, ParameterType::STRING]
                );
            }

            $this->addFlash('success', 'User updated successfully.');
            return $this->redirectToRoute('app_users');
        }

        $user = $connection->executeQuery(
            'SELECT u.id, u.name, u.last_name, u.username, u.email, u.role, u.status,
                    p.member_premium, p.language, p.image
             FROM `user` u
             LEFT JOIN (
                 SELECT p1.*
                 FROM profile p1
                 INNER JOIN (
                     SELECT id_user, MAX(id) AS max_id
                     FROM profile
                     GROUP BY id_user
                 ) p2 ON p1.id = p2.max_id
             ) p ON p.id_user = u.id
             WHERE u.id = ?
             LIMIT 1',
            [$id],
            [ParameterType::INTEGER]
        )->fetchAssociative();

        if (!$user) {
            throw $this->createNotFoundException('User not found.');
        }

        $userRows = $this->processUserImages([$user]);
        $user = $userRows[0];

        return $this->render('admin/user_edit.html.twig', [
            'adminImageUrl' => $adminImageUrl,
            'editedUser' => $user,
        ]);
    }

    #[Route('/users/{id}/delete', name: 'app_user_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function userDelete(int $id, Request $request, Connection $connection): Response
    {
        if (!$this->isCsrfTokenValid('delete-user-' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid delete request token.');
            return $this->redirectToRoute('app_users');
        }

        $currentUser = $this->getUser();
        if ($currentUser instanceof User && (int) $currentUser->getId() === $id) {
            $this->addFlash('error', 'You cannot delete your own admin account while logged in.');
            return $this->redirectToRoute('app_users');
        }

        $connection->beginTransaction();
        try {
            $this->deleteFromIfExists($connection, 'profile', 'id_user = ?', [$id], [ParameterType::INTEGER]);
            $this->deleteFromIfExists($connection, 'todo', 'user_id = ?', [$id], [ParameterType::INTEGER]);
            $this->deleteFromIfExists($connection, 'todos', 'user_id = ?', [$id], [ParameterType::INTEGER]);
            $this->deleteFromIfExists($connection, 'messages', 'sender_id = ? OR receiver_id = ?', [$id, $id], [ParameterType::INTEGER, ParameterType::INTEGER]);
            $this->deleteFromIfExists($connection, 'message', 'sender_id = ? OR receiver_id = ?', [$id, $id], [ParameterType::INTEGER, ParameterType::INTEGER]);
            $this->deleteFromIfExists($connection, 'purchase', 'user_id = ?', [$id], [ParameterType::INTEGER]);
            $this->deleteFromIfExists($connection, 'purchases', 'user_id = ?', [$id], [ParameterType::INTEGER]);
            $this->deleteFromIfExists($connection, 'shop', 'user_id = ?', [$id], [ParameterType::INTEGER]);
            $this->deleteFromIfExists($connection, 'shops', 'user_id = ?', [$id], [ParameterType::INTEGER]);

            $connection->executeStatement('DELETE FROM `user` WHERE id = ?', [$id], [ParameterType::INTEGER]);
            $connection->commit();
            $this->addFlash('success', 'User deleted successfully.');
        } catch (\Throwable $e) {
            $connection->rollBack();
            $this->addFlash('error', 'Delete failed: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_users');
    }

    #[Route('/todos', name: 'app_todos')]
    #[IsGranted('ROLE_ADMIN')]
    public function todos(Request $request, Connection $connection): Response
    {
        $adminImageUrl = $this->getCurrentUserProfileImageUrl($connection);
        $search = trim((string) $request->query->get('q', ''));

        $sql = 'SELECT t.id, t.title, t.description, t.status, t.priority, t.category,
                       t.created_at, t.updated_at, u.username, u.email
                FROM todo t
                LEFT JOIN `user` u ON t.user_id = u.id';
        $params = [];
        $types = [];

        if ($search !== '') {
            $sql .= ' WHERE t.title LIKE ? OR u.username LIKE ? OR u.email LIKE ?';
            $searchParam = '%' . $search . '%';
            $params = [$searchParam, $searchParam, $searchParam];
            $types = [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING];
        }

        $sql .= ' ORDER BY t.updated_at DESC, t.id DESC';

        $allTodos = $connection->executeQuery(
            $sql,
            $params,
            $types
        )->fetchAllAssociative();

        $totalTodos = (int) $connection->fetchOne('SELECT COUNT(*) FROM todo');

        // Count todos by status
        $statusCounts = $connection->executeQuery(
            'SELECT status, COUNT(*) as count FROM todo GROUP BY status'
        )->fetchAllAssociative();

        $todoColumns = [
            'To Do' => [],
            'In Progress' => [],
            'Done' => [],
        ];

        foreach ($allTodos as $todo) {
            $status = trim((string) ($todo['status'] ?? 'To Do'));
            $normalized = strtoupper(str_replace(['-', '_'], ' ', $status));
            if ($normalized === 'DONE') {
                $column = 'Done';
            } elseif ($normalized === 'IN PROGRESS') {
                $column = 'In Progress';
            } else {
                $column = 'To Do';
            }
            $todoColumns[$column][] = $todo;
        }

        return $this->render('admin/todos.html.twig', [
            'adminImageUrl' => $adminImageUrl,
            'todos' => $allTodos,
            'todoColumns' => $todoColumns,
            'totalTodos' => $totalTodos,
            'search' => $search,
            'statusCounts' => $statusCounts,
        ]);
    }

    #[Route('/todos/{id}/status', name: 'app_todo_status_update', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function updateTodoStatus(int $id, Request $request, Connection $connection): JsonResponse
    {
        $newStatus = trim((string) $request->request->get('status', 'To Do'));
        $allowed = ['To Do', 'In Progress', 'Done'];
        if (!in_array($newStatus, $allowed, true)) {
            return $this->json(['success' => false, 'error' => 'Invalid status'], 400);
        }

        $updated = $connection->executeStatement(
            'UPDATE todo SET status = ?, updated_at = NOW() WHERE id = ?',
            [$newStatus, $id],
            [ParameterType::STRING, ParameterType::INTEGER]
        );

        return $this->json(['success' => $updated > 0]);
    }

    #[Route('/admin/stats', name: 'app_admin_stats')]
    #[IsGranted('ROLE_ADMIN')]
    public function adminStats(Connection $connection): Response
    {
        $adminImageUrl = $this->getCurrentUserProfileImageUrl($connection);

        $ageRanges = [
            '13-17' => 0,
            '18-24' => 0,
            '25-34' => 0,
            '35-44' => 0,
            '45-54' => 0,
            '55+' => 0,
        ];

        $ageRows = $connection->executeQuery(
            'SELECT
                CASE
                    WHEN TIMESTAMPDIFF(YEAR, `date`, CURDATE()) BETWEEN 13 AND 17 THEN "13-17"
                    WHEN TIMESTAMPDIFF(YEAR, `date`, CURDATE()) BETWEEN 18 AND 24 THEN "18-24"
                    WHEN TIMESTAMPDIFF(YEAR, `date`, CURDATE()) BETWEEN 25 AND 34 THEN "25-34"
                    WHEN TIMESTAMPDIFF(YEAR, `date`, CURDATE()) BETWEEN 35 AND 44 THEN "35-44"
                    WHEN TIMESTAMPDIFF(YEAR, `date`, CURDATE()) BETWEEN 45 AND 54 THEN "45-54"
                    ELSE "55+"
                END AS age_range,
                COUNT(*) AS count_users
             FROM `user`
             WHERE `date` IS NOT NULL
             GROUP BY age_range'
        )->fetchAllAssociative();

        foreach ($ageRows as $row) {
            $key = (string) ($row['age_range'] ?? '');
            if (array_key_exists($key, $ageRanges)) {
                $ageRanges[$key] = (int) ($row['count_users'] ?? 0);
            }
        }

        $tierRows = $connection->executeQuery(
            'SELECT LOWER(COALESCE(member_premium, "standard")) AS tier, COUNT(*) AS count_profiles
             FROM profile
             GROUP BY tier'
        )->fetchAllAssociative();

        $membershipTiers = [
            'standard' => 0,
            'premium' => 0,
            'vip' => 0,
            'vip+' => 0,
        ];

        foreach ($tierRows as $row) {
            $tier = (string) ($row['tier'] ?? 'standard');
            if (array_key_exists($tier, $membershipTiers)) {
                $membershipTiers[$tier] = (int) ($row['count_profiles'] ?? 0);
            }
        }

        return $this->render('admin/stats.html.twig', [
            'adminImageUrl' => $adminImageUrl,
            'ageRanges' => $ageRanges,
            'membershipTiers' => $membershipTiers,
        ]);
    }

    #[Route('/admin/shop', name: 'app_admin_shop', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminShop(Request $request, Connection $connection): Response
    {
        $adminImageUrl = $this->getCurrentUserProfileImageUrl($connection);

        $schemaManager = $connection->createSchemaManager();
        $shopTable = null;
        foreach (['shop', 'shops'] as $candidate) {
            if ($schemaManager->tablesExist([$candidate])) {
                $shopTable = $candidate;
                break;
            }
        }

        if ($shopTable === null) {
            $this->addFlash('error', 'Shop table was not found in database.');
            return $this->render('admin/shop.html.twig', [
                'adminImageUrl' => $adminImageUrl,
                'products' => [],
            ]);
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('shop-product-create', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Invalid request token. Please try again.');
                return $this->redirectToRoute('app_admin_shop');
            }

            $name = trim((string) $request->request->get('name', ''));
            $description = trim((string) $request->request->get('description', ''));
            $category = trim((string) $request->request->get('category', ''));
            $priceCoins = (int) $request->request->get('price_coins', 0);
            $quantity = (int) $request->request->get('quantity', 0);
            $imageBlob = null;

            if ($name === '') {
                $this->addFlash('error', 'Product name is required.');
                return $this->redirectToRoute('app_admin_shop');
            }

            if ($priceCoins < 0 || $quantity < 0) {
                $this->addFlash('error', 'Price and quantity must be 0 or greater.');
                return $this->redirectToRoute('app_admin_shop');
            }

            $imageFile = $request->files->get('image');
            if ($imageFile !== null) {
                if (!$imageFile->isValid()) {
                    $this->addFlash('error', 'Uploaded image is invalid.');
                    return $this->redirectToRoute('app_admin_shop');
                }

                $imageData = @file_get_contents($imageFile->getPathname());
                if ($imageData !== false && $imageData !== '') {
                    $imageBlob = $imageData;
                }
            }

            try {
                $connection->executeStatement(
                    'INSERT INTO ' . $shopTable . ' (name, description, price_coins, quantity, image, category, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())',
                    [
                        $name,
                        $description !== '' ? $description : null,
                        $priceCoins,
                        $quantity,
                        $imageBlob,
                        $category !== '' ? $category : null,
                    ],
                    [
                        ParameterType::STRING,
                        ParameterType::STRING,
                        ParameterType::INTEGER,
                        ParameterType::INTEGER,
                        ParameterType::LARGE_OBJECT,
                        ParameterType::STRING,
                    ]
                );

                $this->addFlash('success', 'Product added successfully.');
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Unable to add product: ' . $e->getMessage());
            }

            return $this->redirectToRoute('app_admin_shop');
        }

        $products = [];
        try {
            $products = $connection->executeQuery(
                'SELECT id, name, description, price_coins, quantity, image, category, created_at, updated_at
                 FROM ' . $shopTable . '
                 ORDER BY id DESC'
            )->fetchAllAssociative();

            foreach ($products as &$product) {
                $product['image_url'] = $this->imageToUrl($product['image'] ?? null);
            }
            unset($product);
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Unable to load products: ' . $e->getMessage());
        }

        return $this->render('admin/shop.html.twig', [
            'adminImageUrl' => $adminImageUrl,
            'products' => $products,
        ]);
    }

    #[Route('/admin/shop/{id}/delete', name: 'app_admin_shop_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminShopDelete(int $id, Request $request, Connection $connection): Response
    {
        if (!$this->isCsrfTokenValid('shop-product-delete-' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid delete request token.');
            return $this->redirectToRoute('app_admin_shop');
        }

        $shopTable = $this->resolveExistingTableName($connection, ['shop', 'shops']);
        if ($shopTable === null) {
            $this->addFlash('error', 'Shop table was not found in database.');
            return $this->redirectToRoute('app_admin_shop');
        }

        $deleted = $connection->executeStatement(
            'DELETE FROM ' . $shopTable . ' WHERE id = ?',
            [$id],
            [ParameterType::INTEGER]
        );

        if ($deleted > 0) {
            $this->addFlash('success', 'Product deleted successfully.');
        } else {
            $this->addFlash('error', 'Product not found or already deleted.');
        }

        return $this->redirectToRoute('app_admin_shop');
    }

    #[Route('/my-orders', name: 'app_my_orders', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function myOrders(Connection $connection): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $purchaseTable = $this->resolveExistingTableName($connection, ['purchases', 'purchase']);
        $shopTable = $this->resolveExistingTableName($connection, ['shop', 'shops']);
        $orders = [];

        if ($purchaseTable !== null) {
            $sql = 'SELECT p.id, p.shop_id, p.quantity, p.total_coins, p.buyer_name, p.buyer_email, p.buyer_address, p.status, p.purchase_date';
            if ($shopTable !== null) {
                $sql .= ', s.name AS product_name';
            }

            $sql .= ' FROM ' . $purchaseTable . ' p';
            if ($shopTable !== null) {
                $sql .= ' LEFT JOIN ' . $shopTable . ' s ON s.id = p.shop_id';
            }

            $sql .= ' WHERE p.user_id = ? ORDER BY p.purchase_date DESC, p.id DESC';

            $orders = $connection->executeQuery(
                $sql,
                [(int) $user->getId()],
                [ParameterType::INTEGER]
            )->fetchAllAssociative();
        } else {
            $this->addFlash('error', 'Purchases table was not found in database.');
        }

        return $this->render('shop/my_orders.html.twig', [
            'orders' => $orders,
        ]);
    }

    #[Route('/admin/orders', name: 'app_admin_orders', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminOrders(Connection $connection): Response
    {
        $adminImageUrl = $this->getCurrentUserProfileImageUrl($connection);
        $purchaseTable = $this->resolveExistingTableName($connection, ['purchases', 'purchase']);
        $shopTable = $this->resolveExistingTableName($connection, ['shop', 'shops']);
        $orders = [];

        if ($purchaseTable !== null) {
            $sql = 'SELECT p.id, p.user_id, p.shop_id, p.quantity, p.total_coins, p.buyer_name, p.buyer_email, p.buyer_address, p.status, p.purchase_date,
                           u.username AS username';
            if ($shopTable !== null) {
                $sql .= ', s.name AS product_name';
            }

            $sql .= ' FROM ' . $purchaseTable . ' p
                      LEFT JOIN `user` u ON u.id = p.user_id';
            if ($shopTable !== null) {
                $sql .= ' LEFT JOIN ' . $shopTable . ' s ON s.id = p.shop_id';
            }

            $sql .= ' ORDER BY p.purchase_date DESC, p.id DESC';

            $orders = $connection->executeQuery($sql)->fetchAllAssociative();
        } else {
            $this->addFlash('error', 'Purchases table was not found in database.');
        }

        return $this->render('admin/orders.html.twig', [
            'adminImageUrl' => $adminImageUrl,
            'orders' => $orders,
        ]);
    }

    #[Route('/admin/orders/{id}/status', name: 'app_admin_order_status_update', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminOrderStatusUpdate(int $id, Request $request, Connection $connection): Response
    {
        if (!$this->isCsrfTokenValid('order-status-' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid request token.');
            return $this->redirectToRoute('app_admin_orders');
        }

        $status = strtolower(trim((string) $request->request->get('status', 'pending')));
        $allowedStatuses = ['pending', 'confirmed', 'cancelled', 'delivered'];
        if (!in_array($status, $allowedStatuses, true)) {
            $this->addFlash('error', 'Invalid status selected.');
            return $this->redirectToRoute('app_admin_orders');
        }

        $purchaseTable = $this->resolveExistingTableName($connection, ['purchases', 'purchase']);
        if ($purchaseTable === null) {
            $this->addFlash('error', 'Purchases table was not found in database.');
            return $this->redirectToRoute('app_admin_orders');
        }

        $updated = $connection->executeStatement(
            'UPDATE ' . $purchaseTable . ' SET status = ? WHERE id = ?',
            [$status, $id],
            [ParameterType::STRING, ParameterType::INTEGER]
        );

        if ($updated > 0) {
            $this->addFlash('success', 'Order status updated successfully.');
        } else {
            $this->addFlash('error', 'Order not found or unchanged.');
        }

        return $this->redirectToRoute('app_admin_orders');
    }

    #[Route('/reservations', name: 'app_reservations')]
    #[IsGranted('ROLE_USER')]
    public function reservations(): Response
    {
        return $this->render('reservations/index.html.twig');
    }

    #[Route('/my-reservations', name: 'app_my_reservations')]
    #[IsGranted('ROLE_USER')]
    public function myReservations(): Response
    {
        return $this->render('reservations/my_reservations.html.twig');
    }

    #[Route('/offers-grid', name: 'app_offers_grid')]
    #[IsGranted('ROLE_AGENCY')]
    public function offersGrid(): Response
    {
        return $this->render('offers/grid.html.twig');
    }

    #[Route('/shop', name: 'app_shop')]
    #[IsGranted('ROLE_USER')]
    public function shop(Connection $connection): Response
    {
        return $this->render('shop/full.html.twig', [
            'shop' => $this->buildShopViewData($connection),
        ]);
    }

    #[Route('/shop/order', name: 'app_shop_order', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function placeShopOrder(Request $request, Connection $connection, MailerInterface $mailer): Response
    {
        $redirectToMainpageShop = str_contains((string) $request->headers->get('referer', ''), '/mainpage');
        $redirectResponse = $redirectToMainpageShop
            ? $this->redirectToRoute('app_mainpage', ['module' => 'shop'])
            : $this->redirectToRoute('app_shop');

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('shop-order', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid order request token.');
            return $redirectResponse;
        }

        $productId = (int) $request->request->get('product_id', 0);
        $quantity = max(1, (int) $request->request->get('quantity', 1));
        $firstName = trim((string) $request->request->get('first_name', ''));
        $lastName = trim((string) $request->request->get('last_name', ''));
        $buyerEmail = trim((string) $request->request->get('buyer_email', ''));
        $buyerPhone = trim((string) $request->request->get('buyer_phone', ''));
        $buyerAddress = trim((string) $request->request->get('location_text', ''));
        $locationText = trim((string) $request->request->get('location_text', ''));
        $locationLat = trim((string) $request->request->get('location_lat', ''));
        $locationLng = trim((string) $request->request->get('location_lng', ''));
        $buyerName = trim($firstName . ' ' . $lastName);

        if ($productId <= 0 || $firstName === '' || $lastName === '' || $buyerAddress === '' || $buyerPhone === '' || !filter_var($buyerEmail, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('error', 'Please complete all order fields with valid values.');
            return $redirectResponse;
        }

        $shopTable = $this->resolveExistingTableName($connection, ['shop', 'shops']);
        $purchaseTable = $this->resolveExistingTableName($connection, ['purchases', 'purchase']);
        if ($shopTable === null || $purchaseTable === null) {
            $this->addFlash('error', 'Shop or purchases table not found in database.');
            return $redirectResponse;
        }

        $connection->beginTransaction();
        $productName = '';
        $totalCoins = 0;

        try {
            $product = $connection->executeQuery(
                'SELECT id, name, price_coins, quantity FROM ' . $shopTable . ' WHERE id = ? LIMIT 1',
                [$productId],
                [ParameterType::INTEGER]
            )->fetchAssociative();

            if (!$product) {
                throw new \RuntimeException('Selected product does not exist.');
            }

            $stock = (int) ($product['quantity'] ?? 0);
            $priceCoins = (int) ($product['price_coins'] ?? 0);

            if ($stock < $quantity) {
                throw new \RuntimeException('Selected quantity is not available in stock.');
            }

            $profile = $this->fetchOrCreateProfileRow($connection, (int) $user->getId());
            if (!$profile || !isset($profile['id'])) {
                throw new \RuntimeException('Profile not found.');
            }

            $currentCoins = (int) ($profile['coins'] ?? 0);
            $totalCoins = $priceCoins * $quantity;

            if ($currentCoins < $totalCoins) {
                throw new \RuntimeException('Not enough coins for this order.');
            }

            $addressPayload = $buyerAddress;
            $addressPayload .= "\nPhone: " . $buyerPhone;
            if ($locationText !== '') {
                $addressPayload .= "\nMap: " . $locationText;
            }
            if ($locationLat !== '' && $locationLng !== '') {
                $addressPayload .= "\nCoordinates: " . $locationLat . ', ' . $locationLng;
            }

            $connection->executeStatement(
                'INSERT INTO ' . $purchaseTable . ' (user_id, shop_id, quantity, total_coins, buyer_name, buyer_email, buyer_address, status, purchase_date)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                [
                    (int) $user->getId(),
                    $productId,
                    $quantity,
                    $totalCoins,
                    $buyerName,
                    $buyerEmail,
                    $addressPayload,
                    'pending',
                ],
                [
                    ParameterType::INTEGER,
                    ParameterType::INTEGER,
                    ParameterType::INTEGER,
                    ParameterType::INTEGER,
                    ParameterType::STRING,
                    ParameterType::STRING,
                    ParameterType::STRING,
                    ParameterType::STRING,
                ]
            );

            $connection->executeStatement(
                'UPDATE profile SET coins = GREATEST(0, COALESCE(coins, 0) - ?) WHERE id = ?',
                [$totalCoins, (int) $profile['id']],
                [ParameterType::INTEGER, ParameterType::INTEGER]
            );

            $connection->executeStatement(
                'UPDATE ' . $shopTable . ' SET quantity = GREATEST(0, quantity - ?) WHERE id = ?',
                [$quantity, $productId],
                [ParameterType::INTEGER, ParameterType::INTEGER]
            );

            $connection->commit();

            $productName = (string) ($product['name'] ?? 'Product');
        } catch (\Throwable $e) {
            $connection->rollBack();
            $this->addFlash('error', 'Order failed: ' . $e->getMessage());
            return $redirectResponse;
        }

        try {
                        $siteName = 'rehltna.tn';
                        $safeProductName = htmlspecialchars($productName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                        $safeBuyerName = htmlspecialchars($buyerName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                        $safeBuyerEmail = htmlspecialchars($buyerEmail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                        $safeBuyerPhone = htmlspecialchars($buyerPhone, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                        $safeBuyerAddress = htmlspecialchars($buyerAddress, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                        $safeLocationText = htmlspecialchars($locationText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                        $safeCoordinates = htmlspecialchars($locationLat . ', ' . $locationLng, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

                        $locationRows = '';
                        if ($locationText !== '') {
                                $locationRows .= '<tr><td style="padding:8px 0;color:#4b6078;font-weight:700;">Localisation</td><td style="padding:8px 0;color:#0e2340;">' . $safeLocationText . '</td></tr>';
                        }
                        if ($locationLat !== '' && $locationLng !== '') {
                                $locationRows .= '<tr><td style="padding:8px 0;color:#4b6078;font-weight:700;">Coordinates</td><td style="padding:8px 0;color:#0e2340;">' . $safeCoordinates . '</td></tr>';
                        }

                        $emailHtml = '<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation</title>
</head>
<body style="margin:0;padding:0;background:#edf4fb;font-family:Segoe UI,Arial,sans-serif;color:#12263f;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#edf4fb;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="680" cellspacing="0" cellpadding="0" style="max-width:680px;width:100%;background:#ffffff;border-radius:18px;overflow:hidden;border:1px solid #c9d9eb;">
                    <tr>
                        <td style="padding:24px 28px;background:linear-gradient(135deg,#066a8f,#0fa5a2);color:#ffffff;">
                            <div style="font-size:13px;letter-spacing:.8px;text-transform:uppercase;opacity:.9;">' . $siteName . '</div>
                            <h1 style="margin:8px 0 0;font-size:30px;line-height:1.1;">Order Confirmed</h1>
                            <p style="margin:10px 0 0;font-size:15px;opacity:.95;">Thank you for shopping with us. Your order is now registered.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px 28px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
                                <tr>
                                    <td colspan="2" style="font-size:16px;font-weight:800;color:#0f4f7a;padding-bottom:10px;">Order Details</td>
                                </tr>
                                <tr><td style="padding:8px 0;color:#4b6078;font-weight:700;">Product</td><td style="padding:8px 0;color:#0e2340;">' . $safeProductName . '</td></tr>
                                <tr><td style="padding:8px 0;color:#4b6078;font-weight:700;">Quantity</td><td style="padding:8px 0;color:#0e2340;">' . $quantity . '</td></tr>
                                <tr><td style="padding:8px 0;color:#4b6078;font-weight:700;">Total coins</td><td style="padding:8px 0;color:#0e2340;">' . $totalCoins . ' coins</td></tr>
                            </table>

                            <div style="height:1px;background:#dce7f3;margin:18px 0;"></div>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
                                <tr>
                                    <td colspan="2" style="font-size:16px;font-weight:800;color:#0f4f7a;padding-bottom:10px;">Buyer Information</td>
                                </tr>
                                <tr><td style="padding:8px 0;color:#4b6078;font-weight:700;">Name</td><td style="padding:8px 0;color:#0e2340;">' . $safeBuyerName . '</td></tr>
                                <tr><td style="padding:8px 0;color:#4b6078;font-weight:700;">Email</td><td style="padding:8px 0;color:#0e2340;">' . $safeBuyerEmail . '</td></tr>
                                <tr><td style="padding:8px 0;color:#4b6078;font-weight:700;">Phone number</td><td style="padding:8px 0;color:#0e2340;">' . $safeBuyerPhone . '</td></tr>
                                <tr><td style="padding:8px 0;color:#4b6078;font-weight:700;">Address</td><td style="padding:8px 0;color:#0e2340;">' . $safeBuyerAddress . '</td></tr>
                                ' . $locationRows . '
                            </table>

                            <div style="margin-top:20px;padding:14px 16px;background:#f2f9ff;border:1px solid #cbe5ff;border-radius:12px;color:#2c4d6e;font-size:13px;">
                                Need help? Reply to this email and our ' . $siteName . ' support team will assist you.
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';

            $email = (new Email())
                ->from((string) ($_ENV['EMAIL_FROM'] ?? $_SERVER['EMAIL_FROM'] ?? 'noreply@rehletna.tn'))
                ->to($buyerEmail)
                ->subject('Order confirmed - Rehletna Shop')
                ->text(
                    "Order has been confirmed.\n\n" .
                    "Product: {$productName}\n" .
                    "Quantity: {$quantity}\n" .
                    "Total coins: {$totalCoins}\n" .
                    "Name: {$buyerName}\n" .
                    "Email: {$buyerEmail}\n" .
                    "Phone: {$buyerPhone}\n" .
                    "Address: {$buyerAddress}\n" .
                    ($locationText !== '' ? "Map location: {$locationText}\n" : '') .
                    (($locationLat !== '' && $locationLng !== '') ? "Coordinates: {$locationLat}, {$locationLng}\n" : '') .
                    "\nThank you for your purchase."
                )
                ->html($emailHtml);

            $mailer->send($email);
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Order saved, but email could not be sent. Check SMTP settings.');
            return $redirectResponse;
        }

        $this->addFlash('success', 'Order has been confirmed and saved successfully.');
        return $redirectResponse;
    }

    #[Route('/ai-guide', name: 'app_ai_guide')]
    #[IsGranted('ROLE_USER')]
    public function aiGuide(): Response
    {
        return $this->render('ai_guide/index.html.twig');
    }

    #[Route('/chat', name: 'app_chat')]
    #[IsGranted('ROLE_USER')]
    public function chat(): Response
    {
        return $this->render('chat/index.html.twig');
    }

    #[Route('/post', name: 'app_post')]
    #[IsGranted('ROLE_USER')]
    public function post(): Response
    {
        return $this->render('post/index.html.twig');
    }

    #[Route('/services', name: 'app_services')]
    #[IsGranted('ROLE_USER')]
    public function services(): Response
    {
        return $this->render('services/index.html.twig');
    }

    #[Route('/loading', name: 'app_loading')]
    #[IsGranted('ROLE_USER')]
    public function loading(): Response
    {
        return $this->render('home/loading.html.twig');
    }

    private function conversationIdForUser(int $userId): string
    {
        return sprintf('user_%d_admins', $userId);
    }

    private function getAdminUserIds(Connection $connection): array
    {
        $ids = $connection->executeQuery(
            'SELECT id FROM `user` WHERE UPPER(role) IN (?)',
            [['ADMIN', 'ROLE_ADMIN']],
            [ArrayParameterType::STRING]
        )->fetchFirstColumn();

        return array_values(array_unique(array_map('intval', $ids)));
    }

    private function getCurrentUserProfileImageUrl(Connection $connection): string
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return '/images/default_avatar.png';
        }

        $image = $connection->fetchOne(
            'SELECT image FROM profile WHERE id_user = ? LIMIT 1',
            [$user->getId()],
            [ParameterType::INTEGER]
        );

        return $this->imageToUrl($image);
    }

    private function imageToUrl(mixed $image): string
    {
        $default = '/images/default_avatar.png';
        if (empty($image)) {
            return $default;
        }

        try {
            $imageData = $image;
            if (is_resource($imageData)) {
                $imageData = stream_get_contents($imageData);
            }

            if (!is_string($imageData) || $imageData === '') {
                return $default;
            }

            $mime = 'image/jpeg';
            if (substr($imageData, 0, 8) === "\x89PNG\r\n\x1a\n") {
                $mime = 'image/png';
            } elseif (substr($imageData, 0, 2) === "\xFF\xD8") {
                $mime = 'image/jpeg';
            } elseif (substr($imageData, 0, 6) === 'GIF87a' || substr($imageData, 0, 6) === 'GIF89a') {
                $mime = 'image/gif';
            }

            return 'data:' . $mime . ';base64,' . base64_encode($imageData);
        } catch (\Throwable $e) {
            return $default;
        }
    }

    private function buildShopViewData(Connection $connection): array
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $shopTable = $this->resolveExistingTableName($connection, ['shop', 'shops']);
        $products = [];

        if ($shopTable !== null) {
            $rows = $connection->executeQuery(
                'SELECT id, name, description, price_coins, quantity, image, category FROM ' . $shopTable . ' ORDER BY id DESC'
            )->fetchAllAssociative();

            foreach ($rows as $row) {
                $products[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'name' => (string) ($row['name'] ?? ''),
                    'description' => (string) ($row['description'] ?? ''),
                    'price_coins' => (int) ($row['price_coins'] ?? 0),
                    'quantity' => (int) ($row['quantity'] ?? 0),
                    'category' => (string) ($row['category'] ?? ''),
                    'image_url' => $this->imageToUrl($row['image'] ?? null),
                ];
            }
        }

        $profile = $this->fetchOrCreateProfileRow($connection, (int) $user->getId());
        $mapboxToken = (string) ($_ENV['MAPBOX_PUBLIC_TOKEN'] ?? $_SERVER['MAPBOX_PUBLIC_TOKEN'] ?? '');

        return [
            'products' => $products,
            'availableCoins' => (int) ($profile['coins'] ?? 0),
            'mapboxToken' => $mapboxToken,
            'defaultFirstName' => (string) $user->getName(),
            'defaultLastName' => (string) $user->getLastName(),
            'defaultBuyerEmail' => (string) $user->getEmail(),
            'defaultBuyerPhone' => (string) ($user->getStatus() ?? ''),
        ];
    }

    private function deleteFromIfExists(Connection $connection, string $tableName, string $whereSql, array $params, array $types = []): void
    {
        $schemaManager = $connection->createSchemaManager();
        if (!$schemaManager->tablesExist([$tableName])) {
            return;
        }

        $connection->executeStatement(
            'DELETE FROM ' . $tableName . ' WHERE ' . $whereSql,
            $params,
            $types
        );
    }

    private function resolveExistingTableName(Connection $connection, array $candidates): ?string
    {
        $schemaManager = $connection->createSchemaManager();
        foreach ($candidates as $candidate) {
            if ($schemaManager->tablesExist([$candidate])) {
                return $candidate;
            }
        }

        return null;
    }
}