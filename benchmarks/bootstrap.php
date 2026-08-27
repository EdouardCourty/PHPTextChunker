<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

\define('DATASET_DIR', __DIR__ . '/../datasets');
\define('DATASET_BIBLE', DATASET_DIR . '/bible_kjv.txt');
\define('DATASET_LES_MISERABLES', DATASET_DIR . '/les_miserables.txt');
\define('DATASET_BRITANNICA', DATASET_DIR . '/encyclopaedia_britannica_11th.txt');

\define('DATASET_HTML_DIR', DATASET_DIR . '/html');
\define('DATASET_WIKIPEDIA_PHP_EN', DATASET_HTML_DIR . '/wikipedia_php_en.html');
\define('DATASET_WIKIPEDIA_PARIS_FR', DATASET_HTML_DIR . '/wikipedia_paris_fr.html');
\define('DATASET_WIKIPEDIA_EIFFEL_TOWER_FR', DATASET_HTML_DIR . '/wikipedia_eiffel_tower_fr.html');
