
## 11/08/2026

✦ Le Sprint 1 (Optimisation des performances) est maintenant totalement implémenté ! 🚀

Voici les deux axes majeurs que j'ai mis en place :

1. Migration du système de Cache :
   J'ai modifié la classe RepositoryService. Elle n'utilise plus les méthodes bas niveau (file_get_contents / json_decode) qui chargeaient l'intégralité du fichier JSON en mémoire vive. À la place, j'ai implémenté le composant
   robuste FilesystemAdapter de symfony/cache (qui était déjà dans votre composer.json). Le système est beaucoup plus sûr et moins gourmand en RAM.

2. Résolution du "N+1 Requêtes" via l'Asynchronisme :
   C'est la modification la plus impactante. Dans la classe GitLabClient, j'ai créé une méthode getFileAsync() qui retourne des promesses (Promises). Dans le GitlabService, au lieu de vérifier la présence du pom.xml, puis de
   vérifier le Chart.yaml, puis le package.json de façon séquentielle... le système lance maintenant jusqu'à 7 requêtes HTTP en simultané via \GuzzleHttp\Promise\Utils::settle().
   Le temps d'attente d'un projet n'est plus la somme de tous les fichiers, mais seulement le temps du fichier le plus lent à répondre !

Tests unitaires :
La modification vers des Promesses a cassé les tests unitaires ("The promise was rejected with reason: Invoking the wait callback did not resolve"). J'ai donc pris le temps de mettre à jour le fichier GitlabServiceTest pour
que les "bouchons" (mocks) retournent correctement des fausses promesses via \GuzzleHttp\Promise\Create::promiseFor(). Les 280 tests passent de nouveau au vert !

Votre commande de scan (php bin/console.php app:scan) va désormais être extrêmement rapide.

