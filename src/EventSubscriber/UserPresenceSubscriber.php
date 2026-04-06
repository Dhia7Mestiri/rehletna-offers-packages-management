<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class UserPresenceSubscriber implements EventSubscriberInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        $this->updateUserStatus($user, 'online');
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

        $this->updateUserStatus($user, 'offline');
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
