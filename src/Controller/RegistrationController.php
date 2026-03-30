<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register_submit', methods: ['POST'])]
    public function register(
        Request $request, 
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        $firstName = trim((string) $request->request->get('name', ''));
        $lastName = trim((string) $request->request->get('last_name', ''));
        $username = trim((string) $request->request->get('username', ''));
        $email = strtolower(trim((string) $request->request->get('email', '')));
        $birthDate = trim((string) $request->request->get('birth_date', ''));
        $password = (string) $request->request->get('password', '');
        $confirmPassword = (string) $request->request->get('confirm_password', '');
        $role = trim((string) $request->request->get('role', ''));

        // Validate required fields
        if (!$firstName || !$lastName || !$username || !$email || !$birthDate || !$password || !$confirmPassword) {
            $this->addFlash('error', 'All fields are required.');
            return $this->redirectToRoute('app_register');
        }

        // Validate role selection
        if (!$role) {
            $this->addFlash('error', 'Please select at least one role.');
            return $this->redirectToRoute('app_register');
        }

        // Validate role is one of the allowed roles
        $validRoles = ['USER', 'GUIDER', 'AGENCY'];
        if (!in_array($role, $validRoles)) {
            $this->addFlash('error', 'Invalid role selected.');
            return $this->redirectToRoute('app_register');
        }

        // Validate first name and last name: letters only, length 3-12
        if (!preg_match('/^[A-Za-z]{3,12}$/', $firstName)) {
            $this->addFlash('error', 'First name must contain only letters and be between 3 and 12 characters.');
            return $this->redirectToRoute('app_register');
        }
        if (!preg_match('/^[A-Za-z]{3,12}$/', $lastName)) {
            $this->addFlash('error', 'Last name must contain only letters and be between 3 and 12 characters.');
            return $this->redirectToRoute('app_register');
        }

        // Validate username: alphanumeric, length 3-12
        if (!preg_match('/^[A-Za-z0-9]{3,12}$/', $username)) {
            $this->addFlash('error', 'Username must be alphanumeric and be between 3 and 12 characters.');
            return $this->redirectToRoute('app_register');
        }

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('error', 'Please enter a valid email address.');
            return $this->redirectToRoute('app_register');
        }

        // Validate birth date format first
        if (!preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $birthDate)) {
            $this->addFlash('error', 'Birth date must be in DD/MM/YYYY format.');
            return $this->redirectToRoute('app_register');
        }

        $parsedDate = \DateTime::createFromFormat('d/m/Y', $birthDate);
        $dateErrors = \DateTime::getLastErrors();
        $warningCount = is_array($dateErrors) ? $dateErrors['warning_count'] : 0;
        $errorCount = is_array($dateErrors) ? $dateErrors['error_count'] : 0;

        if (!$parsedDate || $warningCount > 0 || $errorCount > 0 || $parsedDate->format('d/m/Y') !== $birthDate) {
            $this->addFlash('error', 'Birth date must be a valid date in DD/MM/YYYY format.');
            return $this->redirectToRoute('app_register');
        }

        // Validate passwords match
        if ($password !== $confirmPassword) {
            $this->addFlash('error', 'Passwords do not match.');
            return $this->redirectToRoute('app_register');
        }

        // Validate password strength
        if (strlen($password) <= 7 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/\d/', $password)) {
            $this->addFlash('error', 'Password must be more than 7 characters and include uppercase, lowercase, and a number.');
            return $this->redirectToRoute('app_register');
        }

        // Check if user exists
        $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            $this->addFlash('error', 'Email already exists.');
            return $this->redirectToRoute('app_register');
        }
        $existingUsername = $entityManager->getRepository(User::class)->findOneBy(['username' => $username]);
        if ($existingUsername) {
            $this->addFlash('error', 'Username already exists.');
            return $this->redirectToRoute('app_register');
        }

        // Create new user
        $user = new User();
        $user->setName($firstName);
        $user->setLastName($lastName);
        $user->setUsername($username);
        $user->setEmail($email);

        // parse birth date from DD/MM/YYYY format
        $user->setDate($parsedDate);
        
        // Set role from form
        $user->setRole($role);
        
        // Hash password
        $hashedPassword = $passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        // Save user
        $entityManager->persist($user);
        $entityManager->flush();

        $this->addFlash('success', 'Account created successfully! Please login.');
        return $this->redirectToRoute('app_login');
    }
}