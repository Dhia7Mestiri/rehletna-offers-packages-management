<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class UserPresenceSubscriber implements EventSubscriberInterface
{
    private const TWO_FACTOR_SESSION_KEY = 'two_factor_verified';

    public function __construct(
        private Connection $connection,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private Security $security,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LogoutEvent::class => 'onLogout',
            KernelEvents::REQUEST => ['onKernelRequest', 8],
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        $this->updateUserStatus($user, 'online');

        if (!$user instanceof User || $user->getId() === null) {
            return;
        }

        $request = $event->getRequest();
        $session = $request->getSession();
        if (!$session) {
            return;
        }

        if (!$this->isTwoFactorEnabled((int) $user->getId())) {
            $session->set(self::TWO_FACTOR_SESSION_KEY, true);
            $session->remove('two_factor_pending_user_id');
            return;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiry = new \DateTimeImmutable('+10 minutes');

        $this->connection->executeStatement(
            'UPDATE `user` SET two_factor_code = ?, two_factor_expiry = ? WHERE id = ?',
            [$code, $expiry->format('Y-m-d H:i:s'), (int) $user->getId()],
            [ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER]
        );

        try {
            $email = (new Email())
                ->from((string) ($_ENV['EMAIL_FROM'] ?? $_SERVER['EMAIL_FROM'] ?? 'noreply@rehletna.tn'))
                ->to((string) $user->getEmail())
                ->subject('Your Rehletna 2FA verification code')
                ->text(
                    "Hello " . (string) ($user->getFullName() ?: $user->getUsername()) . ",\n\n"
                    . "Your verification code is: {$code}\n"
                    . "This code expires in 10 minutes."
                )
                ->html(
                    '<div style="font-family:Segoe UI,Arial,sans-serif;background:#f3f8ff;padding:24px;">'
                    . '<div style="max-width:620px;margin:0 auto;background:#ffffff;border:1px solid #d6e6f7;border-radius:16px;overflow:hidden;box-shadow:0 10px 28px rgba(10,42,76,0.12);">'
                    . '<div style="background:linear-gradient(135deg,#0f7ab0,#0BA4A1);padding:18px 20px;color:#fff;">'
                    . '<h2 style="margin:0;font-size:22px;">Two-Factor Verification</h2>'
                    . '<p style="margin:8px 0 0;opacity:.92;">Use this code to complete your login.</p>'
                    . '</div>'
                    . '<div style="padding:22px;color:#1f3f5f;">'
                    . '<p style="margin:0 0 10px;">Your verification code:</p>'
                    . '<div style="display:inline-block;padding:10px 16px;border:1px solid #bfd7ec;border-radius:10px;background:#f7fbff;font-size:28px;font-weight:800;letter-spacing:4px;color:#0f4f79;">'
                    . htmlspecialchars($code, ENT_QUOTES)
                    . '</div>'
                    . '<p style="margin:14px 0 0;line-height:1.6;">This code expires in <strong>10 minutes</strong>.</p>'
                    . '</div>'
                    . '</div>'
                    . '</div>'
                );

            $this->mailer->send($email);
        } catch (\Throwable $e) {
            // Keep login flow active even if email sending fails.
        }

        $session->set(self::TWO_FACTOR_SESSION_KEY, false);
        $session->set('two_factor_pending_user_id', (int) $user->getId());
        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_two_factor_verify')));
    }

    public function onLogout(LogoutEvent $event): void
    {
        $token = $event->getToken();
        if ($token === null) {
            return;
        }

        $user = $token->getUser();
        if (!$user instanceof UserInterface) {
            return;
        }

        $request = $event->getRequest();
        $session = $request->getSession();
        if ($session) {
            $session->remove(self::TWO_FACTOR_SESSION_KEY);
            $session->remove('two_factor_pending_user_id');
        }

        $this->updateUserStatus($user, 'offline');
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = (string) $request->attributes->get('_route', '');
        if ($route === '') {
            return;
        }

        $allowedRoutes = [
            'app_login',
            'app_logout',
            'app_two_factor_verify',
            'app_two_factor_verify_submit',
            'app_two_factor_resend',
            'app_set_locale',
            'app_collect_coin_status',
            'app_collect_coin',
        ];

        if (in_array($route, $allowedRoutes, true)) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User || $user->getId() === null) {
            return;
        }

        if (!$this->isTwoFactorEnabled((int) $user->getId())) {
            return;
        }

        $session = $request->getSession();
        if (!$session) {
            return;
        }

        if ($session->get(self::TWO_FACTOR_SESSION_KEY, false) === true) {
            return;
        }

        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_two_factor_verify')));
    }

    private function isTwoFactorEnabled(int $userId): bool
    {
        $columnName = $this->resolveTwoFactorColumnName();

        return (bool) $this->connection->fetchOne(
            sprintf('SELECT %s FROM `user` WHERE id = ?', $columnName),
            [$userId],
            [ParameterType::INTEGER]
        );
    }

    private function resolveTwoFactorColumnName(): string
    {
        $columns = $this->connection->executeQuery(
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

    private function updateUserStatus(UserInterface $user, string $status): void
    {
        if ($user instanceof User && $user->getId() !== null) {
            $this->connection->executeStatement(
                'UPDATE `user` SET status = ? WHERE id = ?',
                [$status, $user->getId()],
                [ParameterType::STRING, ParameterType::INTEGER]
            );

            return;
        }

        $identifier = trim((string) $user->getUserIdentifier());
        if ($identifier === '') {
            return;
        }

        $this->connection->executeStatement(
            'UPDATE `user` SET status = ? WHERE email = ?',
            [$status, $identifier],
            [ParameterType::STRING, ParameterType::STRING]
        );
    }
}
