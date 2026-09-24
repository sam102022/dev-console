<?php
declare(strict_types=1);

namespace App\controller;

use App\exception\TechnicalException;
use App\factory\LoggerFactory;
use App\service\GitlabService;
use App\service\RepositoryService;
use App\util\DatagridHelper;
use App\util\UtilsLog;
use Monolog\Logger;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class TagAdminController
{
    public const string ROUTE_TAGS = 'tags';
    public const string ACTION_GET_DATAGRID_ROWS = 'getDatagridRows';
    public const string ACTION_ADD_PROJECT_TAG = 'addProjectTag';
    public const string ACTION_REMOVE_PROJECT_TAG = 'removeProjectTag';

    private readonly Logger $logger;

    public function __construct(
        private readonly RepositoryService $repositoryService,
        private readonly GitlabService $gitlabService,
        private readonly Environment $twig,
        LoggerFactory $loggerFactory
    ) {
        $this->logger = $loggerFactory->get(self::class);
    }

    /**
     * @param array<string, mixed> $messages
     */
    public function index(array &$messages): void
    {
        try {
            $viewModel = [];
            $viewModel['current_route'] = self::ROUTE_TAGS;
            $viewModel['allTags'] = $this->repositoryService->getAllTags();
            echo $this->twig->render('tags.html.twig', $viewModel);
        } catch (LoaderError|RuntimeError|SyntaxError|TechnicalException $e) {
            $this->logger->error(UtilsLog::prefixLog(self::class, __FUNCTION__, __LINE__) . $e->getMessage());
        }
    }

    public function handleRequest(string $action): string
    {
        switch ($action) {
            case self::ACTION_GET_DATAGRID_ROWS:
                return $this->getDatagridRows();
            case self::ACTION_ADD_PROJECT_TAG:
                return $this->addProjectTag();
            case self::ACTION_REMOVE_PROJECT_TAG:
                return $this->removeProjectTag();
            default:
                http_response_code(400);
                return json_encode(['success' => false, 'error' => "Action inconnue: $action"]);
        }
    }

    private function getDatagridRows(): string
    {
        try {
            $projects = $this->gitlabService->scan() ?? [];
        } catch (TechnicalException $e) {
            $this->logger->error(UtilsLog::prefixLog(self::class, __FUNCTION__, __LINE__) . $e->getMessage());
            $projects = [];
        }

        $tagsByProject = $this->repositoryService->getTagsByProject();

        $items = [];
        foreach ($projects as $project) {
            $projectName = $project->getName();
            $tags = $tagsByProject[$projectName] ?? $project->getTags() ?? [];
            $items[] = [
                'name' => $projectName,
                'domain' => $project->getDomain(),
                'tags' => array_values($tags)
            ];
        }

        $filters = [];
        foreach ($_REQUEST as $key => $val) {
            if (str_starts_with($key, 'filter_')) {
                $filters[str_replace('filter_', '', $key)] = $val;
            }
        }

        $sortCol = $_REQUEST['sort_column'] ?? 'name';
        if (empty($sortCol)) {
            $sortCol = 'name';
        }
        $sortDir = $_REQUEST['sort_dir'] ?? 'asc';
        $page = (int)($_REQUEST['p'] ?? 1);
        $limit = (int)($_REQUEST['rows_per_page'] ?? 15);
        if ($limit === 1000) {
            $limit = 999999;
        }

        $paginated = DatagridHelper::process($items, $filters, $sortCol, $sortDir, $page, $limit);

        try {
            $html = $this->twig->render('common/_tags_rows.html.twig', [
                'results' => $paginated['items'],
                'offset' => ($page - 1) * $limit
            ]);
        } catch (LoaderError|RuntimeError|SyntaxError $e) {
            $this->logger->error(UtilsLog::prefixLog(self::class, __FUNCTION__, __LINE__) . $e->getMessage());
            $html = '';
        }

        return json_encode([
            'success' => true,
            'html' => $html,
            'totalRows' => $paginated['totalRows']
        ]);
    }

    private function addProjectTag(): string
    {
        if (($_SESSION['user_role'] ?? '') !== 'ROLE_ADMIN') {
            http_response_code(403);
            return json_encode(['success' => false, 'error' => 'Accès réservé aux administrateurs.']);
        }

        $validated = $this->validateTagInput();
        if ($validated === null) {
            http_response_code(400);
            return json_encode(['success' => false, 'error' => 'Paramètres invalides.']);
        }

        $this->repositoryService->addProjectTag($validated['projectName'], $validated['tag']);
        $tags = $this->repositoryService->getTagsForProject($validated['projectName']);

        return json_encode(['success' => true, 'tags' => $tags]);
    }

    private function removeProjectTag(): string
    {
        if (($_SESSION['user_role'] ?? '') !== 'ROLE_ADMIN') {
            http_response_code(403);
            return json_encode(['success' => false, 'error' => 'Accès réservé aux administrateurs.']);
        }

        $validated = $this->validateTagInput();
        if ($validated === null) {
            http_response_code(400);
            return json_encode(['success' => false, 'error' => 'Paramètres invalides.']);
        }

        $this->repositoryService->removeProjectTag($validated['projectName'], $validated['tag']);
        $tags = $this->repositoryService->getTagsForProject($validated['projectName']);

        return json_encode(['success' => true, 'tags' => $tags]);
    }

    /**
     * @return array{projectName: string, tag: string}|null
     */
    private function validateTagInput(): ?array
    {
        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true);
        if (!is_array($input) || empty($input)) {
            $input = !empty($_POST) ? $_POST : $_REQUEST;
        }

        $projectName = trim((string)($input['projectName'] ?? ''));
        $tag = trim((string)($input['tag'] ?? ''));

        if ($projectName === '' || $tag === '') {
            return null;
        }

        if (mb_strlen($tag) < 3 || mb_strlen($tag) > 50) {
            return null;
        }

        return ['projectName' => $projectName, 'tag' => $tag];
    }
}
