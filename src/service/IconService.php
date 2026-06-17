<?php
declare(strict_types=1);

namespace App\service;

/**
 * Classe IconService
 *
 * Fournit un accès centralisé aux icônes de l'application en utilisant le design pattern Singleton.
 * Charge un fichier de configuration d'icônes et permet de les récupérer par leur clé.
 */
class IconService
{
    public const ICON_CALENDAR = 'calendar';
    public const ICON_CANCEL = 'cancel';
    public const ICON_CATEGORY = 'category';
    public const ICON_COPY = 'copy';
    public const ICON_DIRECTOR = 'director';
    public const ICON_DELETE = 'delete';
    public const ICON_DOWN = 'down';
    public const ICON_DOWNLOAD = 'download';
    public const ICON_DURATION = 'duration';
    public const ICON_EDIT = 'edit';
    public const ICON_EXCLUDE = 'exclude';
    public const ICON_EXCLUDE_RULE = 'exclude_rule';
    public const ICON_EXTERNAL_LINK = 'external_link';
    public const ICON_FAVORITE = 'favorite';
    public const ICON_GRID = 'grid';
    public const ICON_IPTV = 'iptv';
    public const ICON_LOCAL = 'local';
    public const ICON_LOADER = 'loader';
    public const ICON_LIST = 'list';
    public const ICON_LIVE = 'live';
    public const ICON_LOGOUT = 'logout';
    public const ICON_MOVIE = 'movie';
    public const ICON_OFFLINE = 'offline';
    public const ICON_PLAY = 'play';
    public const ICON_PLUS = 'plus';
    public const ICON_RATING = 'rating';
    public const ICON_RATING_HALF = 'rating_half';
    public const ICON_REFRESH = 'refresh';
    public const ICON_REPARE = 'repare';
    public const ICON_RADIO = 'radio';
    public const ICON_SEARCH = 'search';
    public const ICON_SERIE = 'serie';
    public const ICON_SERVER = 'server';
    public const ICON_SETTINGS = 'settings';
    public const ICON_STREAM = 'stream';
    public const ICON_UP = 'up';
    public const ICON_UPDATE = 'update';
    public const ICON_UNLOCK = 'unlock';
    public const ICON_USER = 'user';
    public const ICON_WATCH = 'watch';

    private array $icons = [];

    private static ?self $instance = null;

    /**
     * Constructeur privé pour implémenter le pattern Singleton.
     * @param string $file Chemin du fichier de configuration des icônes.
     */
    private function __construct(string $file = __DIR__ . '/../icons/icons.php')
    {
        if (file_exists($file)) {
            $this->icons = include $file; // NOSONAR
        }
    }

    /**
     * Récupère l'instance unique du service.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Récupère le balisage HTML d'une icône par sa clé.
     * @param string $key Clé de l'icône.
     */
    public function get(string $key): string
    {
        return $this->getValueByPath($key, $this->icons) ?? '';
    }

    /**
     * Récupère une valeur dans un tableau en utilisant une notation "pointée".
     * @param string $path Chemin de la valeur.
     * @param array $data Tableau des valeurs
     */
    private function getValueByPath(string $path, array $data): string
    {
        $keys = explode('.', $path);
        foreach ($keys as $key) {
            if (!isset($data[$key])) {
                return '';
            }
            $data = $data[$key];
        }
        return $data;
    }

    /**
     * Retourne toutes les icônes chargées.
     */
    public function getAll(): array
    {
        return $this->icons;
    }
}
