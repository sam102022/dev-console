<?php
declare(strict_types=1);

namespace App\controller;

use App\service\RepositoryService;
use App\util\DatagridHelper;
use Twig\Environment;

class UserAdminController
{
    public const string ROUTE_USERS = 'users';
    public const string ACTION_CREATE_USER = 'createUser';
    public const string ACTION_UPDATE_USER = 'updateUser';
    public const string ACTION_DELETE_USER = 'deleteUser';

    public function __construct(
        private readonly RepositoryService $repositoryService,
        private readonly Environment $twig
    ) {}

    public function index(array &$messages): void
    {
        try{
            $viewModel = [];
            $viewModel['current_route'] = self::ROUTE_USERS;
            echo $this->twig->render('users.html.twig', $viewModel);
        } catch (LoaderError|RuntimeError|SyntaxError|TechnicalException $e) {
            $this->logger->error(UtilsLog::prefixLog(self::class, __FUNCTION__, __LINE__) . $e->getMessage());
        }
    }

    public function handleRequest(string $action): string
    {
        switch ($action) {
            case 'getDatagridRows':
                return $this->getDatagridRows();
            case self::ACTION_CREATE_USER:
                return $this->createUser();
            case self::ACTION_UPDATE_USER:
                return $this->updateUser();
            case self::ACTION_DELETE_USER:
                return $this->deleteUser();
            default:
                return json_encode(['success' => false, 'error' => "Action inconnue: $action"]);
        }
    }

    private function getDatagridRows(): string
    {
        $users = $this->repositoryService->getAllUsers();

        $filters = [];
        foreach ($_REQUEST as $key => $val) {
            if (str_starts_with($key, 'filter_')) {
                $filters[str_replace('filter_', '', $key)] = $val;
            }
        }

        $sortCol = $_REQUEST['sort_column'] ?? 'created_at';
        if (empty($sortCol)) $sortCol = 'created_at';
        $sortDir = $_REQUEST['sort_dir'] ?? 'desc';
        $page = (int)($_REQUEST['p'] ?? 1);
        $limit = (int)($_REQUEST['rows_per_page'] ?? 15);
        if ($limit === 1000) $limit = 999999;

        $paginated = DatagridHelper::process($users, $filters, $sortCol, $sortDir, $page, $limit);

        $html = $this->twig->render('common/_users_rows.html.twig', [
            'results' => $paginated['items'],
            'offset' => ($page - 1) * $limit
        ]);

        return json_encode([
            'success' => true,
            'html' => $html,
            'totalRows' => $paginated['totalRows']
        ]);
    }

    private function createUser(): string
    {
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        $role = $data['role'] ?? 'ROLE_USER';

        if (empty($email) || empty($password)) {
            return json_encode(['success' => false, 'error' => 'Email et mot de passe requis.']);
        }

        if ($this->repositoryService->findUserByEmail($email)) {
            return json_encode(['success' => false, 'error' => 'Cet email est déjà utilisé.']);
        }

        $user = [
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'role' => $role
        ];

        $id = $this->repositoryService->saveUser($user);

        return json_encode(['success' => true, 'userId' => $id]);
    }

    private function updateUser(): string
    {
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $id = (int)($data['id'] ?? 0);
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        $role = $data['role'] ?? 'ROLE_USER';

        if ($id <= 0 || empty($email)) {
            return json_encode(['success' => false, 'error' => 'Données invalides.']);
        }

        $user = $this->repositoryService->findUserById($id);
        if (!$user) {
            return json_encode(['success' => false, 'error' => 'Utilisateur introuvable.']);
        }

        // Vérifier unicité email si modifié
        if ($email !== $user['email']) {
            $existing = $this->repositoryService->findUserByEmail($email);
            if ($existing) {
                return json_encode(['success' => false, 'error' => 'Cet email est déjà utilisé.']);
            }
        }

        $user['email'] = $email;
        $user['role'] = $role;

        if (!empty($password)) {
            $user['password_hash'] = password_hash($password, PASSWORD_BCRYPT);
        }

        $this->repositoryService->saveUser($user);

        // Si modification de son propre profil, rafraîchir le rôle en session
        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $id) {
            $_SESSION['user_role'] = $role;
            $_SESSION['user_email'] = $email;
        }

        return json_encode(['success' => true]);
    }

    private function deleteUser(): string
    {
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $id = (int)($data['id'] ?? $_GET['id'] ?? 0);

        if ($id <= 0) {
            return json_encode(['success' => false, 'error' => 'Identifiant invalide.']);
        }

        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $id) {
            return json_encode(['success' => false, 'error' => 'Vous ne pouvez pas supprimer votre propre compte.']);
        }

        $this->repositoryService->deleteUser($id);

        return json_encode(['success' => true]);
    }
}
