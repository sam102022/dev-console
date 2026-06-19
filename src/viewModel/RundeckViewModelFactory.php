<?php
declare(strict_types=1);

namespace App\viewModel;

use App\config\AppConfig;
use App\context\IndexContext;
use App\exception\TechnicalException;
use App\model\EnumDomain;
use App\model\EnumEnvironment;
use App\model\RundeckProject;
use App\service\GitlabService;
use App\service\UtilsService;
use App\util\MonitoringUtils;
use Twig\Environment;

/**
 * Classe IndexViewModelFactory
 *
 * Usine (Factory) pour créer le ViewModel de la page public.
 * Elle agrège et prépare toutes les données nécessaires à l'affichage du template principal.
 */
class RundeckViewModelFactory
{
    /**
     * @var RundeckProject[]
     */
    private array $results = [];

    public function __construct(
        private readonly Environment $twig,
        private readonly AppConfig   $appConfig,
        private readonly GitlabService $gitlabService
    )
    {
    }

    /**
     * @param RundeckProject[]|null $results
     */
    public function setResults(?array $results): void
    {
        $this->results = $results ?? [];
    }

    /**
     * Construit le tableau de variables pour le template.
     *
     * @param IndexContext $indexContext Le contexte de la session.
     * @param array $messages Les messages à afficher.
     * @return array
     * @throws TechnicalException
     */
    public function build(IndexContext $indexContext, array $messages): array
    {
        $theme = $indexContext->getTheme();
        $themesColor = THEMES_COLORS;

        // Préparation des données pour la vue
        $domains = [];
        $sfs = [];
        $formattedResults = [];

        foreach ($this->results as $rundeckProject) {
            $enumDomain = EnumDomain::from($rundeckProject->getDomain());
            $domains[$rundeckProject->getDomain()] = $enumDomain->getName();

            if ($rundeckProject->getSf()) {
                $sfs[$rundeckProject->getSf()] = $rundeckProject->getSf();
            }

            $urlsRundeck = [];
            foreach (EnumEnvironment::cases() as $env) {
                $urlsRundeck[$env->value] = MonitoringUtils::buildRundeckUrlFromRundeckProject($rundeckProject, $env);
            }

            $webUrl = null;
            if(!empty($rundeckProject->getProjectName())){
                $gitlabProject = $this->gitlabService->getProjectByCode($rundeckProject->getProjectName());
                $webUrl = $gitlabProject?->getWebUrl();
            }

            // Formate chaque projet pour inclure les URLs directement
            $formattedResults[] = [
                'name' => $rundeckProject->getName(),
                'domain' => $rundeckProject->getDomain(),
                'domainName' => $enumDomain->getName(),
                'sf' => $rundeckProject->getSf(),
                'category' => $rundeckProject->getCategory(),
                'urlsRundeck' => $urlsRundeck,
                'webUrl' => $webUrl
            ];
        }

        return [
            'baseUrl' => $this->appConfig->getBaseUrl(),
            'bgBody' => $themesColor[$theme]['bgBody'],
            'bgContainer' => $themesColor[$theme]['bgContainer'],
            'bgToolbar' => $themesColor[$theme]['bgToolbar'],
            'colorInverse' => $themesColor[$theme]['colorInverse'],
            'colorLink' => $themesColor[$theme]['colorLink'],
            'colorText' => $themesColor[$theme]['colorText'],
            'domains' => $domains,
            'sfs' => $sfs,
            'results' => $formattedResults, // Utilise les résultats formatés
            'messageResults' => UtilsService::buildAlertHtml($this->twig, $messages[MESSAGES_RUNDECK_RESULTS] ?? []),
        ];
    }
}
