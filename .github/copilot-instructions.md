# Copilot Instructions for pidev_project

## 1) High-level architecture
- Symfony 7.4 project, MVC + Doctrine + Twig. Key folders:
  - `src/Controller` (controllers with `#[Route]` attributes, e.g., `TodoController`, `HomeController`, `SecurityController`)
  - `src/Entity` (Doctrine entities, e.g., `User`, `Todo`, `Shop`, `Message`, `Purchase`)
  - `src/Form` (form types such as `TodoType`)
  - `src/Service` (`TodoService` is the model for business logic, controllers should use services for non-trivial state changes)
  - `templates/` (Twig views by route/controller namespace)

## 2) Critical flows and service boundaries
- `TodoController` uses `TodoRepository` and `TodoService`; keep persistence/logic in `TodoService` when adding features.
- `RegistrationController` and `SecurityController` are in `src/Entity` namespace path (legacy layout quirk) but both use `App\Controller` namespace and routes `app_register`, `app_login`, `app_logout`.
- `User` performs roles as a single string column `role`; use `getRoles()` in security checks and `ROLE_USER`, `ROLE_GUIDE`, `ROLE_AGENCY` values.
- Database operations via Doctrine ORM, entity mapping by PHP attributes in entities.

## 3) Build/test/debug workflow
- Install dependencies: `composer install` (auto-scripts: cache clear + assets install + importmap install).
- Run local server: `symfony server:start` or `php -S 127.0.0.1:8000 -t public`.
- Run tests: `bin/phpunit` (or `vendor/bin/phpunit`).
- Run migrations: `bin/console doctrine:migrations:migrate`; generate with `bin/console doctrine:migrations:diff`.
- Common quick check: `bin/console debug:router`, `bin/console debug:container`.

## 4) Project-specific conventions
- Routing is done with attributes, no explicit routes YAML for main controllers.
- Controller methods return `Response` and use `$this->render('path.html.twig', [...])`.
- Use flash messages in controller actions (see `TodoController::new`, `complete`, `delete`, and registration success/failure flows).
- `Todo` has fields `isCompleted` and `completedAt` updated in setter; state transitions should go through those APIs.
- Authorization is enforced with `#[IsGranted('ROLE_USER')]` on `TodoController` class.

## 5) Known weirdness/attention points
- `config/packages/security.yaml` currently uses `users_in_memory` provider while app persists `User` entity; inject correctness checks before changing auth code.
- `RegistrationController` location under `src/Entity` is nonstandard but route mapping works by namespace for PSR-4; avoid moving unless updating autoload/namespace.
- `src/Controller/TodoController.php` uses `$this->entityManager` and `$this->todoService` without explicit properties/constructor (this may be incomplete code in existing branch; prefer constructor injection for explicitness).

## 6) Agent coding priorities
- Preserve existing forms and behaviors (e.g., `TodoType`, `User` fields mandatory/unique constraints on username/email).
- Do not introduce global side effects into controller tests; focus on `WebTestCase` existing pattern.
- Prefer adding services to `config/services.yaml` when explicit configuration needed (autowire is enabled by default).

---

Feedback request: Is this summary useful and precise? Point out one area (security provider, entity location, naming convention) to tighten in the next pass.