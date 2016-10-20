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

use Geocoder\Adapter\GeoIP2Adapter;
use Geocoder\Provider\Chain;
use Geocoder\Provider\FreeGeoIp;
use Geocoder\Provider\GeoIP2;
use Geocoder\Provider\GoogleMaps;
use Geocoder\Provider\HostIp;
use Geocoder\ProviderAggregator;
use GeoIp2\Database\Reader;
use Ivory\HttpAdapter\CurlHttpAdapter;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class Geo
{
    public function get($address = null, $db = 'GeoLite2-City.mmdb')
    {
        if ($address === null) {
            $address = ip();
        }

        //http://ipinfo.io/119.63.142.37/json

        $geocoder = new ProviderAggregator();
        $adapter  = new CurlHttpAdapter();

        $reader        = new Reader(STORAGE.'/'.$db);
        $geoIP2Adapter = new GeoIP2Adapter($reader);

        $chain = new Chain([
            new GeoIP2($geoIP2Adapter),
            new FreeGeoIp($adapter),
            new HostIp($adapter),
            new GoogleMaps($adapter),
        ]);

        $geocoder->registerProvider($chain);

        $results = false;

        try {
            $results = $geocoder->geocode($address);
        } catch (\Exception $e) {
            $results = false;
        }

        $result = [];
        if ($results !== false) {
            foreach ($results as $value) {
                try {
                    $region = $value->getAdminLevels()->get(1);
                } catch (\Exception $e) {
                    $region = false;
                }

                $result['latitude']     = $value->getLatitude();
                $result['longitude']    = $value->getLongitude();
                $result['country']      = $value->getCountry()->getName();
                $result['country_code'] = $value->getCountryCode();
                $result['city']         = $value->getLocality();
                $result['region']       = ($region) ? $region->getName() : '';
                $result['region_code']  = ($region) ? $region->getCode() : '';
                $result['zipcode']      = $value->getPostalCode();
                $result['locality']     = $value->getSubLocality();
            }
        }

        return array_map('trim', $result);
    }
}
