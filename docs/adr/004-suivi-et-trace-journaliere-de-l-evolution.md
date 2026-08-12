# ADR 004 - Suivi et trace journalière de l'évolution du projet

## Statut

✅ Acceptée

## Contexte

Au fur et à mesure de l'avancement du projet par sprints successifs, de multiples refactorings, des intégrations techniques complexes (moteurs de scoring, agents intelligents IA, modules portefeuilles) et des évolutions de base de données se succèdent. Afin de garantir une traçabilité parfaite, de faciliter l'accueil de nouveaux développeurs ou l'alignement avec les demandes métiers du client, il est indispensable de conserver un historique précis et temporel des modifications apportées au jour le jour.

## Décision

Il est décidé de maintenir à la racine du projet un journal de bord centralisé sous la forme d'un fichier `JOURNAL.md`. 

Ce document contiendra :
1. Les rappels des principes d'architecture fondamentaux du projet (ADRs).
2. Une frise chronologique détaillée listant l'évolution du projet sprint par sprint, avec les dates précises et les versions logicielles associées.
3. Une section dédiée mise à jour de manière journalière décrivant les innovations, refactorings techniques et corrections appliqués le jour même.
4. Le statut opérationnel à l'instant T (compilation, état des suites de tests automatisées, etc.).

## Conséquences

*   **Positive** : Traçabilité optimale du projet, alignement fluide avec l'équipe de développement et le client, visibilité directe de l'avancement concret au quotidien.
*   **Positive** : Simplification des processus d'onboarding et d'audit technique.
*   **Neutre** : Nécessite une rigueur de mise à jour systématique à l'issue de chaque journée de développement ou d'intégration de fonctionnalités majeures.
