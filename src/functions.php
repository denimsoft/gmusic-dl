<?php

function api_get_album_info($album_id, array $config, $proxy_filename)
{
    $album_id = preg_replace('/^(B[a-z0-9]+).*/', '$1', $album_id);
    $text = api_exec("get_album_info('$album_id')", $config, $proxy_filename);

    return $text;
}

function api_get_all_user_playlist_contents(array $config, $proxy_filename)
{
    $text = api_exec('get_all_user_playlist_contents()', $config, $proxy_filename);

    return $text;
}

function api_get_registered_devices(array $config, $proxy_filename)
{
    $text = api_exec("get_registered_devices()", $config, $proxy_filename);

    return $text;
}

function api_get_stream_url($track_id, array $config, $proxy_filename)
{
    $text = api_exec("get_stream_url('$track_id')", $config, $proxy_filename);

    return $text;
}

function api_get_track_info($track_id, array $config, $proxy_filename)
{
    $text = api_exec("get_track_info('$track_id')", $config, $proxy_filename);

    return $text;
}

function api_exec($cmd, array $config, $proxy_filename)
{
    $text = <<<PY
#!/usr/bin/env python

from gmusicapi import Mobileclient
import json

api = Mobileclient()
api.login('{$config['email']}', '{$config['password']}', '{$config['android_id']}')
print json.dumps(api.$cmd)

PY;

    file_put_contents($proxy_filename, $text);

    $python_bin = $config['python_bin'];
    $cmd = escapeshellarg($python_bin) . ' ' . escapeshellarg($proxy_filename);
    $cmd .= ' 2>' . escapeshellarg("$proxy_filename.err");
    $result = exec($cmd);

    if (!$result) {
        $err = file_get_contents("$proxy_filename.err");
        if (preg_match('/\b\w*Error:.+/', $err, $match)) {
            $err = $match[0];
        } else {
            $err = 'UnknownError: An unknown error occurred';
        }
        fwrite(STDERR, "$err\n");
        exit(1);
    }

    // unicode string escape decode
    $replacements = [
        '1[34]'    => '-',
        '1[CD]|33' => '"',
        '1[89]|32' => "'"
    ];

    foreach ($replacements as $pattern => $replacement) {
        $result = preg_replace("/\\\\u20($pattern)/", $replacement, $result);
    }

    return @json_decode($result) ?: $result;
}

function config()
{
    static $config = [];

    if ($config) {
        return $config;
    }

    $keys = [
        'python_bin',
        'email',
        'password',
        'android_id',
        'output_dir'
    ];

    $config = array_combine($keys, array_fill(0, count($keys), ''));

    if (file_exists(__DIR__ . '/../config/config.php')) {
        $config = array_replace($config, require __DIR__ . '/../config/config.php');
        $config = array_intersect_key($config, array_flip($keys));
    }

    if (!$config['python_bin']) {
        $config['python_bin'] = 'python';
    }

    if (!$config['output_dir']) {
        $config['output_dir'] = getcwd();
    }

    foreach ($keys as $key) {
        $env_key = 'GMUSIC_' . strtoupper($key);
        $val = getenv($env_key);

        if (strlen($val)) {
            $config[ $key ] = $val;
        }
    }

    return $config;
}

function download($url, $filename)
{
    $dirname = dirname($filename);

    if (!is_dir($dirname)) {
        mkdir($dirname, 0755, true);
    }

    $hnd = fopen($filename, 'w+');

    curl_setopt_array(
        $ch = curl_init($url),
        [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_WRITEFUNCTION  => function ($res, $data) use ($hnd) {
                return fwrite($hnd, $data);
            }
        ]);

    $result = curl_exec($ch);
    fclose($hnd);

    return $result;
}

function filter_pathname($pathname)
{
    $pathname = strtr($pathname, [':' => ' -', '"' => "'"]);
    $pathname = preg_replace('@[\\\/|]@', ';', $pathname);
    $pathname = preg_replace('/[*?<>]/', '', $pathname);

    $pathname = preg_replace_callback(
        '/\b((?:\w\.)+)(?: |$)/',
        function ($match) {
            return str_replace('.', '', $match[0]);
        },
        $pathname);

    return $pathname;
}

function tag_mp3($filename, $track_info)
{
    $writer = new getid3_writetags();
    $writer->filename = $filename;
    $writer->tagformats = ['id3v1', 'id3v2.3'];

    $tags = [
        'title',
        'artist',
        'album',
        'year',
        'track' => 'trackNumber',
        'genre',
        'band'  => 'albumArtist'
    ];

    foreach ($tags as $tag => $track_info_key) {
        if (!empty($track_info->$track_info_key)) {
            if (is_numeric($tag)) {
                $tag = $track_info_key;
            }

            $writer->tag_data[ $tag ][] = $track_info->$track_info_key;
        }
    }

    $cover_art_filename = dirname($filename) . '/folder.jpg';

    if (is_file($cover_art_filename)) {
        $writer->tag_data['attached_picture'][] = [
            'data'          => file_get_contents($cover_art_filename),
            'picturetypeid' => 2,
            'description'   => 'cover',
            'mime'          => 'image/jpeg'
        ];
    }

    return $writer->WriteTags();
}

function tempfile($prefix = '', $dir = '', $delete_on_exit = true)
{
    static $deletion_queue;

    if (!$dir) {
        $dir = sys_get_temp_dir();
    }

    $filename = tempnam($dir, $prefix);

    if ($delete_on_exit) {
        if ($deletion_queue === null) {
            $deletion_queue = [];

            register_shutdown_function(function () use (&$deletion_queue) {
                foreach ($deletion_queue as $filename) {
                    if (file_exists($filename)) {
                        unlink($filename);
                    }
                }
            });
        }

        $deletion_queue[] = $filename;
    }

    return $filename;
}