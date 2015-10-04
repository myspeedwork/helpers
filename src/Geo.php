<?php

/**
 * This file is part of the Speedwork package.
 *
 * @link http://github.com/speedwork
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Speedwork\Helpers;

/**
 * @vendor "geoip2/geoip2": "dev-master"
 *
 * @author sankar <sankar.suda@gmail.com>
 */
class Geo
{
    public function get($address = null, $db = 'GeoLite2-City.mmdb')
    {
        if ($address == null) {
            $address = ip();
        }

        //http://ipinfo.io/119.63.142.37/json

        $geocoder = new \Geocoder\ProviderAggregator();
        $adapter  = new \Ivory\HttpAdapter\CurlHttpAdapter();

        $reader        = new \GeoIp2\Database\Reader(_PUBLIC_DIR.'/'.$db);
        $geoIP2Adapter = new \Geocoder\Adapter\GeoIP2Adapter($reader);

        $chain = new \Geocoder\Provider\Chain([
            new \Geocoder\Provider\GeoIP2($geoIP2Adapter),
            new \Geocoder\Provider\FreeGeoIp($adapter),
            new \Geocoder\Provider\HostIp($adapter),
            new \Geocoder\Provider\GoogleMaps($adapter),
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
