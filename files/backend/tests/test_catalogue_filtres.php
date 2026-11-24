<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Repository\EquipmentRepository;
use App\Service\EquipmentService;
use Medoo\Medoo;

$database = new Medoo([
    'type'     => 'mysql',
    'host'     => '127.0.0.1',
    'database' => 'bts_web',
    'username' => 'root',
    'password' => 'root',
    'charset'  => 'utf8mb4',
    'error'    => PDO::ERRMODE_EXCEPTION,
]);

$repo    = new EquipmentRepository($database);
$service = new EquipmentService($repo);

function check(string $label, bool $ok, string $detail = ''): void
{
    $tag = $ok ? '[OK]   ' : '[ECHEC]';
    echo "{$tag} {$label}" . ($detail !== '' ? " -> {$detail}" : '') . PHP_EOL;
}

echo '=== DÉBUT DES TESTS : RECHERCHE & FILTRES DU CATALOGUE ===' . PHP_EOL . PHP_EOL;

$idsEquipementsTest = [];
$idCategorieTest    = null;

try {
    // 1. Setup : catégorie + 3 équipements de test (dont 1 masqué)
    $database->insert('CATEGORIE_EQUIPEMENT', [
        'libelle' => 'Categorie Test Filtre',
    ]);
    $idCategorieTest = (int) $database->id();

    $database->insert('EQUIPEMENT', [
        'nom'              => 'Imprimante Laser AlphaTest',
        'statut_technique' => 'EN_SERVICE',
        'localisation'     => 'Zone A - FabLab',
        'controle_requis'  => 0,
        'visible_public'   => 1,
        'id_categorie'     => $idCategorieTest,
    ]);
    $idsEquipementsTest[] = (int) $database->id();

    $database->insert('EQUIPEMENT', [
        'nom'              => 'Decoupeuse Laser BetaTest',
        'statut_technique' => 'HORS_SERVICE',
        'localisation'     => 'Zone B - Atelier',
        'controle_requis'  => 1,
        'visible_public'   => 1,
        'id_categorie'     => $idCategorieTest,
    ]);
    $idsEquipementsTest[] = (int) $database->id();

    $database->insert('EQUIPEMENT', [
        'nom'              => 'Imprimante Cachee GammaTest',
        'statut_technique' => 'EN_SERVICE',
        'localisation'     => 'Reserve',
        'controle_requis'  => 0,
        'visible_public'   => 0,
        'id_categorie'     => $idCategorieTest,
    ]);
    $idsEquipementsTest[] = (int) $database->id();

    // TEST 1 : Sans filtre -> retourne au moins les 2 visibles, jamais le masqué
    $all  = $service->list();
    $noms = array_column($all, 'name');
    check(
        'Test 1 - Liste sans filtre (visibles uniquement)',
        in_array('Imprimante Laser AlphaTest', $noms, true)
        && in_array('Decoupeuse Laser BetaTest', $noms, true)
        && !in_array('Imprimante Cachee GammaTest', $noms, true),
        count($all) . ' équipement(s) retourné(s)'
    );

    // TEST 2 : Recherche par mot-clé partiel (q = "AlphaTest")
    $resQ  = $service->list(['q' => 'AlphaTest']);
    $nomsQ = array_column($resQ, 'name');
    check(
        'Test 2 - Recherche mot-clé q="AlphaTest"',
        count($resQ) === 1 && $nomsQ[0] === 'Imprimante Laser AlphaTest',
        implode(', ', $nomsQ)
    );

    // TEST 3 : Recherche sur la localisation (q = "Zone B")
    $resLoc  = $service->list(['q' => 'Zone B']);
    $nomsLoc = array_column($resLoc, 'name');
    check(
        'Test 3 - Recherche localisation q="Zone B"',
        in_array('Decoupeuse Laser BetaTest', $nomsLoc, true)
        && !in_array('Imprimante Laser AlphaTest', $nomsLoc, true),
        implode(', ', $nomsLoc)
    );

    // TEST 4 : Filtre par catégorie (category = "Categorie Test Filtre")
    $resCat  = $service->list(['category' => 'Categorie Test Filtre']);
    $nomsCat = array_column($resCat, 'name');
    check(
        'Test 4 - Filtre par catégorie',
        count($resCat) === 2
        && in_array('Imprimante Laser AlphaTest', $nomsCat, true)
        && in_array('Decoupeuse Laser BetaTest', $nomsCat, true),
        implode(', ', $nomsCat)
    );

    // TEST 5 : Filtre par statut technique (status = "HORS_SERVICE")
    $resStat  = $service->list(['category' => 'Categorie Test Filtre', 'status' => 'HORS_SERVICE']);
    $nomsStat = array_column($resStat, 'name');
    check(
        'Test 5 - Combinaison catégorie + status="HORS_SERVICE"',
        count($resStat) === 1 && $nomsStat[0] === 'Decoupeuse Laser BetaTest',
        implode(', ', $nomsStat)
    );

    // TEST 6 : Recherche combinée (q="Laser" + status="EN_SERVICE")
    $resCombo  = $service->list(['q' => 'Laser', 'status' => 'EN_SERVICE']);
    $nomsCombo = array_column($resCombo, 'name');
    check(
        'Test 6 - Combinaison q="Laser" + status="EN_SERVICE"',
        in_array('Imprimante Laser AlphaTest', $nomsCombo, true)
        && !in_array('Decoupeuse Laser BetaTest', $nomsCombo, true),
        implode(', ', $nomsCombo)
    );

    // TEST 7 : Aucun résultat -> tableau vide []
    $resVide = $service->list(['q' => 'EquipementInexistantXYZ999']);
    check(
        'Test 7 - Aucun résultat renvoie []',
        $resVide === [],
        'count=' . count($resVide)
    );

    // TEST 8 : Équipement masqué (visible_public = 0) jamais exposé même avec son nom exact
    $resHidden = $service->list(['q' => 'GammaTest']);
    check(
        'Test 8 - Équipement visible_public=0 masqué',
        $resHidden === [],
        'count=' . count($resHidden)
    );
} finally {
    if ($idsEquipementsTest !== []) {
        $database->delete('EQUIPEMENT', ['id_equipement' => $idsEquipementsTest]);
    }
    if ($idCategorieTest !== null) {
        $database->delete('CATEGORIE_EQUIPEMENT', ['id_categorie' => $idCategorieTest]);
    }
    echo PHP_EOL . '[CLEANUP] Données de test catalogue supprimées.' . PHP_EOL;
}