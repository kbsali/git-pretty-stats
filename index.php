<?php
ini_set('memory_limit', '512M');
set_time_limit(300);
require_once __DIR__.'/vendor/autoload.php';

use Symfony\Component\HttpFoundation\Response;

$app = new Silex\Application();

$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/views',
));

$app->error(function(\Exception $e) use($app) {
    return $app["twig"]->render("error.html", array("message" => $e->getMessage()));
});

$app->before(function() use ($app) {
    $repositoriesPath = 'repositories';

    $configFilePath = __DIR__ . '/config.php';
    if (file_exists($configFilePath)) {
        $configFile = require_once __DIR__ . '/config.php';
        if (isset($configFile['repositoriesPath'])) {
            $repositoriesPath = $configFile['repositoriesPath'];
        }
    }

    $config['repositoriesPath'] = $repositoriesPath;

    $app['config'] = $config;
});

function loadRepository ($app, $path) {
    $repositoryPath = $app['config']['repositoriesPath'] . '/' . $path;
    try {
        $gitWrapper = new \PHPGit_Repository(__DIR__ . '/' . $repositoryPath);
        $repository = new PrettyGit\GitRepository($gitWrapper);
        $repository->loadCommits();
        $app['repository'] = $repository;
    } catch (Exception $e) {
        // Catch all possible errors while loading the repository, re-wrap it with a friendlier
        // message and re-throw so it's caught by the error handler:
        // (the original exception is chained to the new one):
        throw new RuntimeException('The repository path does not contain a valid git repository', 0, $e);
    }

    return $repository;
}

$app->get('/', function () use ($app) {
    $repositoriesPath = $app['config']['repositoriesPath'];

    $repositoryList = new PrettyGit\RepositoryList($repositoriesPath);

    return $app['twig']->render(
        'index.html',
        array(
            'repositories' => $repositoryList->getRepositories()
        )
    );
});

$app->get('repository/{path}', function ($path) use ($app) {
    $repository = loadRepository($app, $path);

    return $app['twig']->render(
        'repository.html',
        array(
            'name'          => $repository->getName(),
            'branch'        => $repository->getGitWrapper()->getCurrentBranch(),
            'commits'       => $repository->getNumberOfCommits(),
            'statsEndpoint' => $app["request"]->getBaseUrl() . "/stats/" . $path,
        )
    );
});

$app->get('/stats/{path}', function($path) use($app) {
    $repository = loadRepository($app, $path);

    return $app->json(
        $repository->getStatisticsForIndex()
    );
});

$app['debug'] = true;
$app->run();
