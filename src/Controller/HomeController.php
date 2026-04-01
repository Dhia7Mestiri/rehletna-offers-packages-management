<?php
// src/Controller/HomeController.php

namespace App\Controller;

use App\Entity\User;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    #[Route('/home', name: 'app_home_home')]
    public function index(): Response
    {
        return $this->render('home/index.html.twig');
    }

    #[Route('/mainpage', name: 'app_mainpage')]
    #[IsGranted('ROLE_USER')]
    public function mainpage(Connection $connection): Response
    {
        $user = $this->getUser();
        $profile = null;

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
        ]);
    }

    #[Route('/load-content', name: 'app_load_content', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function loadContent(Request $request): Response
    {
        $view = $request->request->get('view');
        
        // Load different views based on selection
        switch ($view) {
            case 'services':
                return $this->render('partials/services.html.twig');
            case 'activities':
                return $this->render('partials/activities.html.twig');
            case 'shop':
                return $this->render('partials/shop.html.twig');
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

        if ($user) {
            $result = $connection->executeQuery(
                'SELECT id, member_premium FROM profile WHERE id_user = ? LIMIT 1',
                [$user->getId()]
            );
            $profile = $result->fetchAssociative();

            if (!$profile) {
                $connection->executeStatement(
                    'INSERT INTO profile (image, member_premium, language, id_user, coins) VALUES (?, ?, ?, ?, ?)',
                    [null, 'standard', 'English', $user->getId(), 0],
                    [ParameterType::LARGE_OBJECT, ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER, ParameterType::INTEGER]
                );
            }
        }

        return $this->render('premium/subscription.html.twig', [
            'user' => $user,
            'profile' => $profile,
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

    #[Route('/users', name: 'app_users')]
    #[IsGranted('ROLE_ADMIN')]
    public function users(Request $request, Connection $connection): Response
    {
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
    public function shop(): Response
    {
        return $this->render('shop/index.html.twig');
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
}