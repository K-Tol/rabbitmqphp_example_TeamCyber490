#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once(__DIR__ . '/vendor/autoload.php');
}
if (file_exists(__DIR__ . '/apikey.php')) {
    require_once(__DIR__ . '/apikey.php');
}

use Tmdb\Exception\TmdbApiException;
use Tmdb\Model\Search\SearchQuery\MovieSearchQuery;
use Tmdb\Repository\GenreRepository;
use Tmdb\Repository\MovieRepository;
use Tmdb\Repository\SearchRepository;

function tmdbClient()
{
    static $client = null;
    if ($client !== null) {
        return $client;
    }

    $setupPath = __DIR__ . '/setup-client.php';
    if (!file_exists($setupPath)) {
        throw new RuntimeException('setup-client.php not found.');
    }

    $client = require $setupPath;
    return $client;
}

function movieServerClient(): rabbitMQClient
{
    return new rabbitMQClient('movieServer.ini', 'movieServer');
}

function syncMovie(int $tmdbId): array
{
    if ($tmdbId <= 0) {
        return ['ok' => false, 'error' => 'invalid_tmdb_id'];
    }

    try {
        $repository = new MovieRepository(tmdbClient());
        $movie = $repository->load($tmdbId);

        $genreIds = [];
        foreach ($movie->getGenres() as $genre) {
            $genreIds[] = $genre->getId();
        }

        $releaseDate = $movie->getReleaseDate();
        $releaseDateString = $releaseDate ? $releaseDate->format('Y-m-d') : null;

        movieServerClient()->send_request([
            'type' => 'store_movie',
            'genre_ids' => $genreIds,
            'movie' => [
                'tmdb_id' => $movie->getId(),
                'title' => $movie->getTitle(),
                'overview' => $movie->getOverview(),
                'release_date' => $releaseDateString,
                'runtime' => $movie->getRuntime(),
                'poster_path' => $movie->getPosterPath(),
                'backdrop_path' => $movie->getBackdropPath(),
                'original_language' => $movie->getOriginalLanguage(),
                'vote_average' => $movie->getVoteAverage(),
                'vote_count' => $movie->getVoteCount(),
                'popularity' => $movie->getPopularity(),
                'adult' => $movie->getAdult(),
            ],
        ]);

        return ['ok' => true];
    } catch (TmdbApiException $e) {
        if (TmdbApiException::STATUS_RESOURCE_NOT_FOUND === $e->getCode()) {
            return ['ok' => false, 'error' => 'not_found'];
        }
        return ['ok' => false, 'error' => 'tmdb_error', 'details' => $e->getMessage()];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'server_error', 'details' => $e->getMessage()];
    }
}

function syncGenres(): array
{
    try {
        $genreRepository = new GenreRepository(tmdbClient());
        $genres = $genreRepository->loadMovieCollection();

        $client = movieServerClient();
        foreach ($genres as $genre) {
            $client->send_request([
                'type' => 'store_genre',
                'tmdb_genre_id' => $genre->getId(),
                'name' => $genre->getName(),
            ]);
        }

        return ['ok' => true];
    } catch (TmdbApiException $e) {
        if (TmdbApiException::STATUS_RESOURCE_NOT_FOUND === $e->getCode()) {
            return ['ok' => false, 'error' => 'not_found'];
        }
        return ['ok' => false, 'error' => 'tmdb_error', 'details' => $e->getMessage()];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'server_error', 'details' => $e->getMessage()];
    }
}

function syncSearch(string $query): array
{
    $query = trim($query);
    if ($query === '') {
        return ['ok' => true, 'synced' => 0];
    }

    try {
        $searchRepository = new SearchRepository(tmdbClient());
        $results = $searchRepository->searchMovie($query, new MovieSearchQuery());

        $synced = 0;
        foreach ($results as $movie) {
            $tmdbId = (int) $movie->getId();
            if ($tmdbId <= 0) {
                continue;
            }

            $syncResult = syncMovie($tmdbId);
            if (($syncResult['ok'] ?? false) === true) {
                $synced++;
            }
        }

        return ['ok' => true, 'synced' => $synced];
    } catch (TmdbApiException $e) {
        if (TmdbApiException::STATUS_RESOURCE_NOT_FOUND === $e->getCode()) {
            return ['ok' => false, 'error' => 'not_found'];
        }
        return ['ok' => false, 'error' => 'tmdb_error', 'details' => $e->getMessage()];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'server_error', 'details' => $e->getMessage()];
    }
}

function requestProcessor(array $request): array
{
    if (!isset($request['type'])) {
        return ['ok' => false, 'error' => 'unsupported_message_type'];
    }

    switch ($request['type']) {
        case 'sync_movie':
            return syncMovie((int) ($request['tmdb_id'] ?? 0));
        case 'sync_genres':
            return syncGenres();
        case 'sync_search':
            return syncSearch((string) ($request['query'] ?? ''));
        default:
            return ['ok' => false, 'error' => 'unsupported_message_type'];
    }
}

$server = new rabbitMQServer('datasource.ini', 'datasourceServer');
$server->process_requests('requestProcessor');
