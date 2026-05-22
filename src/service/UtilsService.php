<?php
declare(strict_types=1);

namespace App\service;

use App\exception\TechnicalException;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * Classe UtilsService
 *
 * Fournit un ensemble de méthodes utilitaires statiques, principalement pour
 * la construction de composants d'interface utilisateur (HTML).
 */
class UtilsService
{
    /**
     * Construit le HTML pour une ou plusieurs alertes Bootstrap.
     *
     * @param Environment $twig Gestionnaire des templates twig
     * @param array|null $messages Tableau de messages groupés par niveau (info, error, etc.).
     * @throws TechnicalException Erreur dans le chargement du template
     */
    public static function buildAlertHtml(Environment $twig, ?array $messages): string
    {
        $out = '';
        foreach ($messages as $level => $messagesTmp) {
            $alertClassCss = 'secondary';
            if ($level === LEVEL_LOG_INFO) {
                $alertClassCss = 'info';
            } elseif ($level === LEVEL_LOG_WARN) {
                $alertClassCss = 'warning';
            } elseif ($level === LEVEL_LOG_ERROR) {
                $alertClassCss = 'danger';
            }
            foreach ($messagesTmp as $messageTmp) {
                $dataTwig = [
                    'alertClassCss' => $alertClassCss,
                    'message' => $messageTmp
                ];
                try {
                    $out .= $twig->render('common\alert.html.twig', $dataTwig);
                } catch (LoaderError | RuntimeError | SyntaxError $e) {
                    throw TechnicalException::createWithMessage("Erreur dans le chargement du template : " . $e->getMessage());
                }
            }
        }
        return $out;
    }
}
