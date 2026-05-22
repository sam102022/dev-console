<?php
declare(strict_types=1);

namespace App\viewModel;

use App\config\AppConfig;
use App\context\IndexContext;
use App\exception\TechnicalException;
use App\service\Translator;
use App\service\UtilsService;
use App\util\UtilsFile;
use Twig\Environment;

/**
 * Classe AdminViewModelFactory
 *
 * Usine (Factory) pour créer le ViewModel de la page public.
 * Elle agrège et prépare toutes les données nécessaires à l'affichage du template principal de l'administration.
 */
class IndexViewModelFactory
{
    private array $results = [];

    public function __construct(
        private readonly Environment     $twig,
        private readonly AppConfig       $appConfig,
    )
    {
    }

    /**
     * @param array|null $results
     */
    public function setResults(?array $results): void
    {
        $this->results = $results;
    }

    /**
     * Construit le tableau de variables pour le template de la page public.
     *
     * @param IndexContext $indexContext Le contexte de la session public.
     * @param array $messages Les messages à afficher à l'utilisateur.
     * @throws TechnicalException
     */
    public function build(IndexContext $indexContext, array $messages): array
    {
        $theme = $indexContext->getTheme();

        $themesColor = THEMES_COLORS;

        $bgBody = $themesColor[$theme]['bgBody'];
        $bgContainer = $themesColor[$theme]['bgContainer'];
        $bgToolbar = $themesColor[$theme]['bgToolbar'];
        $colorInverse = $themesColor[$theme]['colorInverse'];
        $colorLink = $themesColor[$theme]['colorLink'];
        $colorText = $themesColor[$theme]['colorText'];

        $sfs = [];
        $subsfs = [];
        foreach($this->results as $result){
            $sfs[$result['sf']] = $result['sfName'];
            $subsfs[$result['subsf']] = $result['subsf'];
        }

        return [
            'baseUrl' => $this->appConfig->getBaseUrl(),
            'bgBody' => $bgBody,
            'bgContainer' => $bgContainer,
            'bgToolbar' => $bgToolbar,
            'colorInverse' => $colorInverse,
            'colorLink' => $colorLink,
            'colorText' => $colorText,
            //'containerIndex' => $containerIndex,
            //'javascriptsHead' => $javascriptsHead,
            //'loadHtml' => LOAD_HTML,
            'sfs' => $sfs,
            'subsfs' => $subsfs,
            'results' => $this->results,
            'messageScanResults' => UtilsService::buildAlertHtml($this->twig, $messages[MESSAGES_SCAN_RESULTS]),
        ];
    }
}
