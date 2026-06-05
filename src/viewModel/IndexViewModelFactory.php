<?php
declare(strict_types=1);

namespace App\viewModel;

use App\config\AppConfig;
use App\context\IndexContext;
use App\exception\TechnicalException;
use App\model\Project;
use App\service\UtilsService;
use Twig\Environment;

/**
 * Classe IndexViewModelFactory
 *
 * Usine (Factory) pour créer le ViewModel de la page public.
 * Elle agrège et prépare toutes les données nécessaires à l'affichage du template principal.
 */
class IndexViewModelFactory
{
    private array $results = [];

    public function __construct(
        private readonly Environment $twig,
        private readonly AppConfig   $appConfig
    )
    {
    }

    /**
     * @param Project[]|null $results
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
        $subsfs = [];
        $technos = [];
        $formattedResults = [];

        /** @var Project $project */
        foreach ($this->results as $project) {
            if ($project->getDomain()) {
                $domains[$project->getDomain()] = $project->getDomainName();
            }
            if ($project->getSubsf()) {
                $subsfs[$project->getSubsf()] = $project->getSubsf();
            }
            if ($project->getTechno()) {
                $technos[$project->getTechno()] = $project->getTechno();
            }

            // Formate chaque projet pour inclure les URLs directement
            $formattedResults[] = [
                'name' => $project->getName(),
                'domain' => $project->getDomain(),
                'domainName' => $project->getDomainName(),
                'subsf' => $project->getSubsf(),
                'cloudGCP' => $project->isCloudGCP(),
                'springBoot' => $project->getSpringBoot(),
                'java' => $project->getJava(),
                'mdmWorkloadVersion' => $project->getMdmWorkloadVersion(),
                'techno' => $project->getTechno(),
                'webUrl' => $project->getWebUrl(),
                'archived' => $project->isArchived(),
                'urlHealthCheck' => $project->getUrlHealthCheck(),
                'urlLogs' => $project->getUrlLogs(),
                'urlFronts' => $project->getUrlFronts(),
                'urlPubsubs' => $project->getUrlPubsubs(),
                'urlsRundeck' => $project->getUrlsRundeck(),
                'urlsDeploymentGcp' => $project->getUrlsDeploymentGcp(),
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
            'subsfs' => $subsfs,
            'technos' => $technos,
            'results' => $formattedResults, // Utilise les résultats formatés
            'messageScanResults' => UtilsService::buildAlertHtml($this->twig, $messages[MESSAGES_SCAN_RESULTS] ?? []),
        ];
    }
}
