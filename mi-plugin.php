<?php
/**
 * Plugin Name: Mi Plugin desde GitHub
 * Description: Plugin de WordPress enlazado a GitHub para actualizaciones automáticas.
 * Version: 1.0.0
 * Author: Codigoverso
 * GitHub Plugin URI: https://github.com/Codigoverso/mi-plugin-wp
 */

defined('ABSPATH') or die('Acceso denegado.');

class Mi_Plugin_GitHub_Updater {
    private $github_api_url = 'https://api.github.com/repos/Codigoverso/mi-plugin-wp/releases/latest';
    private $plugin_file = __FILE__;
    private $plugin_slug;
    private $plugin_version;

    public function __construct() {
        $this->plugin_slug = plugin_basename($this->plugin_file);
        $this->plugin_version = get_file_data($this->plugin_file, ['Version' => 'Version'])['Version'];

        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_update']);
        add_filter('plugins_api', [$this, 'plugin_popup_info'], 20, 3);
        add_filter('upgrader_post_install', [$this, 'post_install'], 10, 3);
    }

    public function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        // Obtener información desde GitHub
        $response = wp_remote_get($this->github_api_url, [
            'headers' => ['User-Agent' => 'WordPress']
        ]);

        if (is_wp_error($response)) {
            return $transient;
        }

        $release = json_decode(wp_remote_retrieve_body($response));

        if (!isset($release->tag_name) || !isset($release->assets[0]->browser_download_url)) {
            return $transient;
        }

        $latest_version = $release->tag_name;

        if (version_compare($this->plugin_version, $latest_version, '<')) {
            $transient->response[$this->plugin_slug] = (object) [
                'slug'        => $this->plugin_slug,
                'plugin'      => $this->plugin_slug,
                'new_version' => $latest_version,
                'package'     => $release->assets[0]->browser_download_url,
                'url'         => 'https://github.com/Codigoverso/mi-plugin-wp',
            ];
        }

        return $transient;
    }

    public function plugin_popup_info($result, $action, $args) {
        if ($action !== 'plugin_information' || $args->slug !== $this->plugin_slug) {
            return $result;
        }

        $response = wp_remote_get($this->github_api_url, ['headers' => ['User-Agent' => 'WordPress']]);
        if (is_wp_error($response)) {
            return $result;
        }

        $release = json_decode(wp_remote_retrieve_body($response));

        if (!isset($release->tag_name)) {
            return $result;
        }

        return (object) [
            'name'         => 'Mi Plugin desde GitHub',
            'slug'         => $this->plugin_slug,
            'version'      => $release->tag_name,
            'author'       => '<a href="https://github.com/Codigoverso">Codigoverso</a>',
            'homepage'     => 'https://github.com/Codigoverso/my-plugin-wp',
            'download_link'=> $release->assets[0]->browser_download_url,
            'sections'     => [
                'description' => 'Este plugin se actualiza directamente desde GitHub.',
                'changelog'   => isset($release->body) ? nl2br($release->body) : 'No hay notas de versión.',
            ]
        ];
    }

    public function post_install($response, $hook_extra, $result) {
        global $wp_filesystem;
        $install_dir = plugin_dir_path($this->plugin_file);

        $wp_filesystem->move($result['destination'], $install_dir);
        $result['destination'] = $install_dir;

        return $result;
    }
}

new Mi_Plugin_GitHub_Updater();
