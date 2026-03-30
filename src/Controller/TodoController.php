<?php

namespace App\Controller;

use App\Entity\Todo;
use App\Form\TodoType;
use App\Service\TodoService;
use App\Repository\TodoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/todo')]
#[IsGranted('ROLE_USER')]
class TodoController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TodoService $todoService,
    ) {
    }

    #[Route('/', name: 'app_todo_index', methods: ['GET'])]
    public function index(TodoRepository $todoRepository): Response
    {
        // FOR TESTING ONLY: Create a dummy user if none exists
        $user = $this->getUser();
        
        if (!$user) {
            // For testing, return a simple message
            return new Response('
                <html>
                    <body>
                        <h1>Todo List</h1>
                        <p>Authentication required. Please <a href="/login">login</a> or <a href="/register">register</a>.</p>
                        <p><small>For testing without auth, uncomment the test data in the controller.</small></p>
                    </body>
                </html>
            ');
        }

        $todos = $todoRepository->findByUser($user, true);
        $completedTodos = $todoRepository->findCompletedByUser($user);

        return $this->render('todo/index.html.twig', [
            'todos' => $todos,
            'completed_todos' => $completedTodos,
        ]);
    }

    #[Route('/new', name: 'app_todo_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $todo = new Todo();
        $form = $this->createForm(TodoType::class, $todo);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $todo->setUser($this->getUser());
            $this->entityManager->persist($todo);
            $this->entityManager->flush();

            $this->addFlash('success', 'Todo created successfully!');
            return $this->redirectToRoute('app_todo_index');
        }

        return $this->render('todo/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/complete', name: 'app_todo_complete', methods: ['POST'])]
    public function complete(Todo $todo): Response
    {
        $this->todoService->completeTodo($todo);
        $this->addFlash('success', 'Todo completed!');
        return $this->redirectToRoute('app_todo_index');
    }

    #[Route('/{id}', name: 'app_todo_delete', methods: ['POST'])]
    public function delete(Todo $todo): Response
    {
        $this->entityManager->remove($todo);
        $this->entityManager->flush();
        
        $this->addFlash('success', 'Todo deleted!');
        return $this->redirectToRoute('app_todo_index');
    }

    #[Route('/{id}/state', name: 'app_todo_state_update', methods: ['POST'])]
    public function updateState(Todo $todo, Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('todo-state-' . $todo->getId(), (string) $request->request->get('_token'))) {
            return $this->json(['success' => false, 'error' => 'Invalid token'], 403);
        }

        $desiredState = strtolower(trim((string) $request->request->get('state', 'active')));
        if (!in_array($desiredState, ['active', 'done'], true)) {
            return $this->json(['success' => false, 'error' => 'Invalid state'], 400);
        }

        $shouldBeCompleted = $desiredState === 'done';
        $todo->setIsCompleted($shouldBeCompleted);
        $this->entityManager->flush();

        return $this->json(['success' => true]);
    }
}