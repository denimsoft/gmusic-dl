<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/functions.php';

$request_id = isset($argv[1]) ? $argv[1] : null;

if (!preg_match('/^AMa|[BT][a-z0-9]+|^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $request_id)) {
    fwrite(STDERR, sprintf("Usage: php %s playlist_guid|album_id|track_id\n", basename(__FILE__)));
    fwrite(STDERR, sprintf("       php %s 00000000-1111-2222-3333-444444444444\n", basename(__FILE__)));
    fwrite(STDERR, sprintf("       php %s Bxxxxxxxxxxxxxxxxxxxxxx4\n", basename(__FILE__)));
    fwrite(STDERR, sprintf("       php %s TBxxxxxxxxxxxxxxxxxxxxxx4\n", basename(__FILE__)));
    exit(1);
}

new getID3();
getid3_lib::IncludeDependency(GETID3_INCLUDEPATH . 'write.php', __FILE__, true);

$config = config();
$proxy_filename = tempfile('gmusic');

if ($request_id[0] === 'B') {
    $collection = api_get_album_info($request_id, $config, $proxy_filename);

    if (empty($collection->tracks)) {
        fwrite(STDERR, "Error: album not found\n");
        exit(1);
    }
} elseif ($request_id[0] === 'T') {
    $track_info = api_get_track_info($request_id, $config, $proxy_filename);

    if (empty($track_info->storeId)) {
        fwrite(STDERR, "Error: track not found\n");
        exit(1);
    }

    $collection = (object)array('tracks' => array($track_info));
} else {
    $collection = array_slice(array_filter(
        api_get_all_user_playlist_contents($config, $proxy_filename),
        function ($playlist) use ($request_id) {
            return $playlist->id === $request_id ||
            str_replace('=', '', $playlist->shareToken) === str_replace('=', '', $request_id);
        }), 0, 1);

    if (!isset($collection[0])) {
        fwrite(STDERR, "Error: playlist not found\n");
        exit(1);
    }

    $collection = $collection[0];
}

foreach ($collection->tracks as $track_info) {
    if (!empty($track_info->track)) {
        $track_info = $track_info->track;
    }

    if (empty($track_info->storeId)) {
        continue;
    }

    $track_dirname = sprintf(
        '%s/%s/%s',
        $config['output_dir'],
        filter_pathname($track_info->albumArtist),
        filter_pathname($track_info->album));

    $mp3_filename = "$track_dirname/" . sprintf(
        '%02d %s.mp3',
        $track_info->trackNumber,
        filter_pathname($track_info->title));

    if (is_file($mp3_filename)) {
        continue;
    }

    echo "$mp3_filename\n";

    $cover_art_filename = "$track_dirname/folder.jpg";

    if (!is_file($cover_art_filename)) {
        download($track_info->albumArtRef[0]->url, $cover_art_filename);
    }

    $stream_url = api_get_stream_url($track_info->storeId, $config, $proxy_filename);
    download($stream_url, $mp3_filename);

    tag_mp3($mp3_filename, $track_info);
}