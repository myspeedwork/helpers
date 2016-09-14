<?php

/*
 * This file is part of the Speedwork package.
 *
 * (c) Sankar <sankar.suda@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code
 */

namespace Speedwork\Helpers;

use Aws\S3\S3Client;
use Exception;
use League\Flysystem\Adapter\AwsS3;
use League\Flysystem\Adapter\Ftp;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Adapter\Sftp;
use League\Flysystem\Filesystem;
use League\Flysystem\MountManager;
use Speedwork\Core\Helper;

/**
 * @author Sankar <sankar.suda@gmail.com>
 */
class Transfer extends Helper
{
    public $debug = true;

    public function start($ids = null, $cond = [])
    {
        $transport = $this->get('resolver')->helper('transport');

        // Get all sources for frequency
        $conditions = [];

        if (!empty($cond)) {
            $conditions = $cond;
        }

        $conditions[] = ['tt.status' => 1];

        if ($this->get['id']) {
            $ids = explode(',', $this->get['id']);
        }

        $task  = $this->get['task'];
        $check = true;

        $joins = [];
        if ($ids) {
            $joins[] = [
                'table'      => '#__transport_process',
                'alias'      => 'tp',
                'type'       => 'INNER',
                'conditions' => ['tp.fk_transfer_id = tt.id'],
            ];

            $conditions[] = ['tp.id' => $ids];

            $check = false;
        }

        $action = $this->get['action'];
        if ($action) {
            $action       = explode(',', $action);
            $conditions[] = ['tt.action' => $action];
        }

        $service = $this->get['service'];
        if ($service) {
            $service      = explode(',', $service);
            $conditions[] = ['tt.service' => $service];
        }

        $rows = $this->database->find('#__transport_transfer', 'all', [
            'conditions' => $conditions,
            'joins'      => $joins,
            'alias'      => 'tt',
            'fields'     => ['tt.*'],
        ]);

        foreach ($rows as $row) {
            $id      = $row['id'];
            $service = '['.$id.'] :'.$row['service'];

            // check frequency came
            if ($check && $row['frequency']) {
                if (is_numeric($row['frequency'])) {
                    if (($row['lastrun'] + $row['frequency']) > time()) {
                        $this->output('Time not reached for service id:'.$id, $service);
                        continue;
                    }
                }
            }

            $meta              = json_decode($row['meta_value'], true);
            $meta['userid']    = $row['fkuserid'];
            $meta['id']        = $id;
            $meta['service']   = $row['service'];
            $meta['timestamp'] = time();
            $meta['date']      = date('Ymd');
            $meta['datetime']  = date('Ymdhis');
            $meta['time']      = date('hmi');

            $action = strtolower($row['action']);
            $list   = ($meta['list']) ? 'database' : 'fly';

            if ($check && !$this->isTimeCame($meta['frequency'])) {
                $this->output('Time not come for service id:'.$row['id'], $service);
                continue;
            }

            $source = $transport->getConfig($row['fk_source_config_id']);
            $dest   = $transport->getConfig($row['fk_dest_config_id']);

            if (empty($source['adapter'])) {
                $this->output('Adapter not found', $service);
                continue;
            }

            // update last run
            $this->database->update('#__transport_transfer',
                ['lastrun' => time()],
                ['id'      => $id]
            );

            // add source adapter
            $config   = json_decode($source['config'], true);
            $adapter1 = strtolower($source['adapter']);
            $manager  = null;

            try {
                $manager = $this->getManager($adapter1, $config, $meta, 'source');
            } catch (\Exception $e) {
                $this->output('Unable to connect to Adapter '.$adapter1.' e:'.$e->getMessage(), $service);
                continue;
            }

            $managers = [];

            if ($manager) {
                $adapter1            = $adapter1.'s';
                $managers[$adapter1] = $manager;
                $adapter1            = $adapter1.'://';
            } else {
                $this->output('Unable to connect to Adapter '.$adapter1, $service);
                continue;
            }

            $adapter2 = null;
            //add destination adapter
            if ($dest['adapter']) {
                $config   = json_decode($dest['config'], true);
                $adapter2 = strtolower($dest['adapter']);
                $manager  = null;

                try {
                    $manager = $this->getManager($adapter2, $config, $meta, 'dest');
                } catch (\Exception $e) {
                    $this->output('Unable to connect to Adapter '.$adapter2.' e:'.$e->getMessage(), $service);
                    continue;
                }

                if ($manager) {
                    $adapter2            = $adapter2.'d';
                    $managers[$adapter2] = $manager;
                    $adapter2            = $adapter2.'://';
                } else {
                    $this->output('Unable to connect to Adapter '.$adapter2, $service);
                    continue;
                }
            }

            if (empty($managers)) {
                $this->output('MountManager not found.', $service);
                continue;
            }

            // Add them in the constructor
            $manager = new MountManager($managers);

            if ($task == 'list') {
                echo 'Lising ..('.$adapter1.')'."\n";
                printr($this->listContents($manager, $adapter1, $id, $list, $ids, $meta));
                break;
            }

            if ($task == 'list2') {
                echo 'Lising ..('.$adapter2.')'."\n";
                printr($this->listContents($manager, $adapter2, $id, $list, $ids, $meta));
                break;
            }

            $this->output('Reading contents from '.$list, $service);

            try {
                $contents = $this->listContents($manager, $adapter1, $id, $list, $ids, $meta);
            } catch (Exception $e) {
                $this->output('Unable to list content from Adapter '.$adapter1.' e:'.$e->getMessage(), $service);
            }

            if (empty($contents)) {
                $this->output('Contents not found', $service);
                continue;
            }

            if ($action == 'delete') {
                $eids = $this->deleteAction($manager, $adapter1, $adapter2, $contents, $row);
            }

            if ($action == 'rename') {
                $eids = $this->renameAction($manager, $adapter1, $adapter2, $contents, $row);
            }

            if (in_array($action, ['get', 'put'])) {
                $eids = $this->getOrPutAction($manager, $adapter1, $adapter2, $contents, $row, $meta, $action, $service);
            }

            if ($list == 'database') {
                if (!empty($eids)) {
                    $this->database->update('#__transport_process',
                        ['status' => 1, 'modified' => time()],
                        ['id'     => $eids]
                    );
                }

                // update the increments
                $eids = [];
                foreach ($contents as $content) {
                    $eids[] = $content['id'];
                }

                if (!empty($eids)) {
                    $this->database->update('#__transport_process',
                        ['attempt = attempt + 1'],
                        ['id' => $eids]
                    );
                }
            }
        }
    }

    private function listContents(&$manager, $adapter = null, $id = null, $list = null, $ids = null, &$meta = [])
    {
        if ($list == 'database') {
            $conditions   = [];
            $conditions[] = ['status' => 0];
            if ($ids) {
                $conditions[] = ['id' => $ids];
            } else {
                $conditions[] = ['fk_transfer_id' => $id];
            }

            $rows = $this->database->find('#__transport_process', 'all', [
                'conditions' => $conditions,
                'limit'      => 1000,
            ]);

            $contents = [];

            foreach ($rows as $row) {
                $files = json_decode($row['transfer_files'], true);
                foreach ($files as $name => $file) {
                    $name       = (is_numeric($name)) ? $this->nameFormat($file, $meta) : $name;
                    $contents[] = [
                        'id'       => $row['id'],
                        'type'     => 'file',
                        'basename' => $file,
                        'path'     => $file,
                        'name'     => $name,
                        'meta'     => json_decode($row['meta_value'], true),
                    ];
                }
            }

            return $contents;
        }

        $pattern   = $meta['pattern'] ? '/'.trim($meta['pattern'], '/').'/' : '';
        $recursive = ($meta['recursive']) ? true : false;
        $contents  = $manager->listContents($adapter, $recursive);

        $results = [];
        foreach ($contents as &$row) {
            if (!empty($pattern)) {
                $basename = $row['basename'];
                if (!preg_match($pattern, $basename)) {
                    $this->output($basename.' file not matched pattern ['.$pattern.']');
                    continue;
                }
            }
            $row['name'] = $this->nameFormat($row['basename'], $meta);
            $results[]   = $row;
        }

        return $results;
    }

    /**
     * Transport sources.
     *
     * @param string $adapter [description]
     * @param array  $config  [description]
     *
     * @return [type] [description]
     */
    private function getManager($adapter, $config = [], &$meta = [], $type = null)
    {
        $config['root'] = isset($config['root']) ? $config['root'] : '/';

        if ($meta['append'] && empty($meta['append_adapter'])) {
            $config['root'] = $config['root'].'/'.$meta['append'].'/';
        } elseif ($meta['append'] && $meta['append_adapter'] == $adapter) {
            $config['root'] = $config['root'].'/'.$meta['append'].'/';
        } elseif ($meta['append'] && $type && $meta['append_adapter'] == $type) {
            $config['root'] = $config['root'].'/'.$meta['append'].'/';
        }

        if ($type && is_array($meta['prefix']) && isset($meta['prefix'][$type])) {
            $config['root'] = $config['root'].'/'.$meta['prefix'][$type].'/';
        }

        $config['root'] = preg_replace('|\/+|', '/', $config['root']);
        $config['root'] = isset($config['root']) ? $config['root'] : '/';

        if (is_array($meta)) {
            foreach ($meta as $key => $value) {
                $config['root'] = str_replace('{'.$key.'}', $value, $config['root']);
            }
        }

        switch ($adapter) {
            case 'ftp':
                try {
                    $ftp = new Ftp($config);
                    $ftp->connect();
                } catch (Exception $e) {
                    $this->output('Unable to connect to Adapter '.$adapter.' e:'.$e->getMessage());

                    return false;
                }

                return new Filesystem($ftp);
                break;
            case 'sftp':
                try {
                    $sftp = new Sftp($config);
                    $sftp->connect();
                } catch (Exception $e) {
                    $this->output('Unable to connect to Adapter '.$adapter.' e:'.$e->getMessage());

                    return false;
                }

                return new Filesystem($sftp);
                break;

            case 's3':
            case 'aws':
                try {
                    $client = S3Client::factory([
                        'key'    => $config['key'],
                        'secret' => $config['secret'],
                        'region' => $config['region'],
                    ]);
                } catch (Exception $e) {
                    $this->output('Unable to connect to Adapter '.$adapter.' e:'.$e->getMessage());

                    return false;
                }

                try {
                    $s3 = new AwsS3($client, $config['bucket'], $config['root']);
                } catch (Exception $e) {
                    $this->output('Unable to connect to Adapter '.$adapter.' e:'.$e->getMessage());

                    return false;
                }

                return new Filesystem($s3);
                break;
            case 'local':
                return new Filesystem(
                    new Local($config['root'])
                );
                break;
        }

        return false;
    }

    private function isTimeCame($frequencies = [])
    {
        if (empty($frequencies) || !is_array($frequencies)) {
            return true;
        }

        foreach ($frequencies as $frequency) {
            $type  = $frequency['type'];
            $start = $frequency['start'];
            $end   = $frequency['end'];

            if (empty($start)) {
                $this->output('Start date is not proper');
                continue;
            }

            if ($type != 'date' && empty($end)) {
                $this->output('End date is not proper');
                continue;
            }

            $start = ($start) ? strtotime(str_replace('/', '-', $start)) : '';
            $end   = ($end) ? strtotime(str_replace('/', '-', $end)) : '';

            $time = time();

            if ($type == 'dates') {
                if ($start <= $time && $end >= $time) {
                    return true;
                }
            } elseif ($type == 'date') {
                $date = strtotime(date('Y-m-d'));
                if ($date == $start) {
                    return true;
                }
            } elseif ($type == 'day') {
                $day = strtolower(date('D'));
                if ($frequency['day'] && $day != $frequency['day']) {
                    $this->output('Today is not '.$frequency['day']);
                    continue;
                }
                if ($start > $end) {
                    $end += 24 * 60 * 60;
                }

                if ($time >= $start && $time <= $end) {
                    return true;
                }
            }
        }

        return false;
    }

    private function nameFormat($file, &$meta = [])
    {
        $format = $meta['format'];

        if (empty($format)) {
            return $file;
        }

        $ext = strrchr($file, '.');

        if (is_array($meta)) {
            $meta['ext'] = $ext;

            foreach ($meta as $key => $value) {
                $format = str_replace('{'.$key.'}', $value, $format);
            }
        }

        return ($format) ? $format : $file;
    }

    private function output($message, $append = null)
    {
        $message = $message.' '.$append;
        if ($this->debug) {
            echo '['.date('d M').'] '.$message."\n";
        }

        $this->get('loger')->info($message);
    }

    protected function getOrPutAction(&$manager, $adapter1, $adapter2, $contents, $row, $meta, $action, $service)
    {
        $service = '['.$row['id'].'] :'.$row['service'];

        $eids = [];
        if (in_array($action, ['get', 'put'])) {
            foreach ($contents as $entry) {
                if ($entry['type'] != 'file' || empty($entry['basename'])) {
                    continue;
                }

                $basename = $entry['path'];
                $name     = $entry['name'];
                $key      = $entry['id'];
                $pattern  = $meta['pattern'];

                if (!empty($pattern)) {
                    $pattern = '/'.trim($pattern, '/').'/';
                    if (!preg_match($pattern, $basename)) {
                        $this->output($basename.' file not matched pattern ['.$pattern.'] :'.$adapter1, $service);
                        continue;
                    }
                }

                // Find file exists in source
                if (!$manager->has($adapter1.$basename)) {
                    $this->output($basename.' file not found :'.$adapter1, $service);
                    continue;
                }

                if ($manager->has($adapter2.$name)) {
                    if (!empty($meta['overwrite'])) {
                        $this->output($name.' already exists (delete)'.$adapter2, $service);
                        $manager->delete($adapter2.$name);
                    } else {
                        $this->output($name.' already exists:'.$adapter2, $service);
                        continue;
                    }
                }

                try {
                    $manager->writeStream($adapter2.$name,
                        $manager->readStream($adapter1.$basename)
                    );
                } catch (Exception $e) {
                    $this->output($name.' unable to '.$action.' e:'.$e->getMessage(), $service);
                    continue;
                }

                $eids[] = $key;
                $this->output($basename.' '.$action.': from:'.$adapter1.$basename.' to:'.$adapter2.$name, $service);

                if (!empty($meta['process'])) {
                    $save                   = [];
                    $save['fkuserid']       = $row['fkuserid'];
                    $save['fk_transfer_id'] = $row['id'];
                    $save['service']        = $row['service'];
                    $save['created']        = time();
                    $save['basename']       = $name;
                    $save['transfer_files'] = json_encode($entry);
                    $save['meta_value']     = json_encode(['entry' => $entry['meta'], 'row' => $meta]);
                    $save['status']         = 2;

                    $this->database->save('#__transport_process', $save);
                }

                $events = $meta['events'];
                if (!is_array($events)) {
                    $events = explode(',', $events);
                }

                if (is_array($events) && !empty($events)) {
                    foreach ($events as $event) {
                        if (empty($event)) {
                            continue;
                        }

                        try {
                            $eventHelper = $this->application->helper($event);
                        } catch (Exception $e) {
                            $this->output($event.' event not found. e:'.$e->getMessage(), $service);
                            continue;
                        }

                        if (method_exists($eventHelper, 'run')) {
                            $eventHelper->run($entry, $row);
                        } elseif (method_exists($eventHelper, 'execute')) {
                            $eventHelper->execute($entry, $row);
                        } else {
                            $this->output($event.' has no run($entry, $row) method.', $service);
                            continue;
                        }
                    }
                }

                //delete from source
                if (!empty($meta['delete'])) {
                    try {
                        $manager->delete($adapter1.$basename);
                    } catch (Exception $e) {
                        $this->output($basename.' unable to delete e:'.$e->getMessage(), $service);
                        continue;
                    }

                    $this->output($basename.' deleted:'.$adapter1, $service);
                }

                //rename in source
                if (!empty($meta['rename'])) {
                    try {
                        $manager->rename($adapter1.$basename, $basename.'.rd');
                    } catch (Exception $e) {
                        $this->output($basename.' unable to rename e:'.$e->getMessage(), $service);
                        continue;
                    }

                    $this->output($basename.' renamed:'.$adapter1, $service);
                }
            }
        }

        return $eids;
    }

    protected function renameAction(&$manager, $adapter1, $adapter2, $contents = [], $row = [])
    {
        $service = '['.$row['id'].'] :'.$row['service'];
        $action  = 'rename';

        $eids = [];
        // Rename source
        foreach ($contents as $key => $entry) {
            $basename = $entry['basename'];

            if (!$manager->has($adapter1.$basename)) {
                $this->output($basename.' not found to '.$action.':'.$adapter1, $service);
                continue;
            }

            try {
                $manager->rename($adapter1.$basename, $basename.'.rd');
            } catch (Exception $e) {
                $this->output($entry['basename'].' unable to '.$action.' e:'.$e->getMessage(), $service);
                continue;
            }

            $this->output($basename.' renamed:'.$adapter1, $service);

            $eids[] = $key;
        }

        if ($adapter2) {
            //rename destination
            foreach ($contents as $key => $entry) {
                $basename = $entry['name'];
                if (!$manager->has($adapter2.$basename)) {
                    $this->output($basename.' not found to '.$action.':'.$adapter2, $service);
                    continue;
                }

                try {
                    $manager->rename($adapter2.$basename, $basename.'.rd');
                } catch (Exception $e) {
                    $this->output($basename.' unable to '.$action.' e:'.$e->getMessage(), $service);
                    continue;
                }

                $this->output($basename.' renamed:'.$adapter2, $service);

                $eids[] = $key;
            }
        }

        return $eids;
    }

    protected function deleteAction(&$manager, $adapter1, $adapter2, $contents = [], $row = [])
    {
        $service = '['.$row['id'].'] :'.$row['service'];
        $action  = 'delete';
        $eids    = [];
        // delete from source
        foreach ($contents as $key => $entry) {
            $basename = $entry['basename'];

            if (!$manager->has($adapter1.$basename)) {
                $this->output($basename.' not found to '.$action.':'.$adapter1, $service);
                continue;
            }

            try {
                $manager->delete($adapter1.$basename);
            } catch (Exception $e) {
                $this->output($basename.' unable to '.$action.' e:'.$e->getMessage(), $service);
                continue;
            }

            $this->output($basename.' deleted:'.$adapter1, $service);

            $eids[] = $key;
        }

        if ($adapter2) {
            //delete from destination
            foreach ($contents as $key => $entry) {
                $basename = $entry['name'];
                if (!$manager->has($adapter2.$basename)) {
                    $this->output($basename.' not found to '.$action.':'.$adapter2, $service);
                    continue;
                }

                try {
                    $manager->delete($adapter2.$basename);
                } catch (Exception $e) {
                    $this->output($basename.' unable to '.$action.' e:'.$e->getMessage(), $service);
                    continue;
                }

                $this->output($basename.' deleted:'.$adapter2, $service);

                $eids[] = $key;
            }
        }
    }
}
