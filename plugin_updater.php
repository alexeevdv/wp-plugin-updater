<?php
/*
The MIT License (MIT)

Copyright (c) 2015 Dmitry V. Alexeev <mail@alexeevdv.ru>

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
*/

// Prevent direct access to file
if (!function_exists('add_action')) exit;

// If file was already included
if (class_exists("PluginUpdater_0_1_0")) return;

class PluginUpdater_0_1_0
{
    // address to version.json file
    public $url = false;
    // updates check period
    public $period = 3600;

    protected $plugin_file = false;
    protected $plugin_name = false;

    public function __construct($params)
    {
        if (!is_array($params)) return;
        foreach($params as $name => $value)
        {
            if (!property_exists($this, $name)) continue;
            $this->$name = $value;
        }

        if ($this->url === false || $this->plugin_name == false) return;

        $this->plugin_file = $this->plugin_name."/".$this->plugin_name.".php";

        add_filter('site_transient_update_plugins', array($this, 'filter_site_transient_update_plugins'));
    }

    public function filter_site_transient_update_plugins($updates)
    {
        if (!is_object($updates))
        {
            $updates = new \stdClass();
            $updates->response = array();
        }

        // Check that we are in admin section
        if (!is_admin()) return $updates;

        $update_info = get_transient(md5($this->plugin_name."_update_info"));

        if ($update_info === false)
        {
            $update_info = $this->fetch_update_info();
            set_transient(md5($this->plugin_name."_update_info"), $update_info, $this->period);            
        }
        if (!$update_info) return $updates;

        $plugin_info = get_plugin_data(WP_PLUGIN_DIR."/".$this->plugin_file);        

        if ($update_info->new_version != $plugin_info['Version'])
        {
            $updates->response[$this->plugin_file] = $update_info;
        }
        return $updates;    
    }

    protected function fetch_update_info()
    {
        $response = wp_remote_get($this->url, array(
            'timeout' => 3,
            'headers' => array(
                'Accept' => 'application/json',
            ),
        ));
        
        // If can't connect to server
        if (is_object($response) && is_a($response, 'WP_Error'))
        {
            return null;
        }

        // If version file not found
        if ($response['response']['code'] != 200)
        {
            return null;
        }

        $json = json_decode($response['body'], true);

        if (!isset($json['version']) || !isset($json['package']) || !isset($json['url']))
        {
            return null;
        }

        return (object)array(
            "id" => rand(1, 99999),
            "slug" => $this->plugin_name,
            "plugin" => $this->plugin_file,
            "new_version" => $json['version'],
            "url" => $json['url'],
            "package" => $json['package'],
        );
    }
}

