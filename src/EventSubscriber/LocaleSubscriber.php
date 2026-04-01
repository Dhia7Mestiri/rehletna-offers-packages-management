<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class LocaleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly Connection $connection,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 20],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $session = $request->getSession();

        if ($session && $session->has('_locale')) {
            $locale = (string) $session->get('_locale');
            if (in_array($locale, ['en', 'fr'], true)) {
                $request->setLocale($locale);
            }
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        $profileLanguage = (string) $this->connection->fetchOne(
            'SELECT LOWER(COALESCE(language, "english")) FROM profile WHERE id_user = ? ORDER BY id DESC LIMIT 1',
            [(int) $user->getId()],
            [ParameterType::INTEGER]
        );

        $locale = str_starts_with($profileLanguage, 'fr') ? 'fr' : 'en';
        $request->setLocale($locale);
        if ($session) {
            $session->set('_locale', $locale);
        }
    }
}
