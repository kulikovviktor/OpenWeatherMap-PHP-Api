<?php
/**
 * OpenWeatherMap-PHP-API — An php api to parse weather data from http://www.OpenWeatherMap.org .
 *
 * @license MIT
 *
 * Please see the LICENSE file distributed with this source code for further
 * information regarding copyright and licensing.
 *
 * Please visit the following links to read about the usage policies and the license of OpenWeatherMap before using this class.
 * @see http://www.OpenWeatherMap.org
 * @see http://www.OpenWeatherMap.org/about
 * @see http://www.OpenWeatherMap.org/copyright
 * @see http://openweathermap.org/appid
 */

namespace cmfcmf\OpenWeatherMap;

use cmfcmf\OpenWeatherMap,
    cmfcmf\OpenWeatherMap\Exception as OWMException,
    cmfcmf\OpenWeatherMap\Util\City,
    cmfcmf\OpenWeatherMap\WeatherForecast;

/**
 * Weather class returned by cmfcmf\OpenWeatherMap->getWeather().
 *
 * @see cmfcmf\OpenWeatherMap::getWeather() The function using it.
 */
class Forecast implements \Iterator
{
    /**
     * The copyright notice. This is no offical text, this hint was made regarding to http://www.http://openweathermap.org/copyright.
     *
     * @var $copyright
     *
     * @see http://www.http://openweathermap.org/copyright http://www.http://openweathermap.org/copyright
     */
    public $copyright = "Weather data from <a href=\"http://www.openweathermap.org\">OpenWeatherMap.org</a>";

    /**
     * A city object.
     *
     * @var Util\City
     */
    public $city;

    /**
     * The time of the last update of this weather data.
     *
     * @var \DateTime
     */
    public $lastUpdate;

    /**
     * An array of {@link WeatherForecast} objects.
     *
     * @var array
     *
     * @see WeatherForecast The WeatherForecast class.
     */
    private $forecasts;

    /**
     * @internal
     */
    private $position = 0;

    /**
     * Create a new Forecast object.
     *
     * @param        $query
     * @param string $units
     * @param string $lang
     * @param string $appid
     * @param        $days
     * @param bool   $cacheClass
     * @param int    $seconds
     *
     * @throws \Exception If the parameters are wrong.
     * @throws OWMException If OpenWeatherMap returns a problem.
     *
     * @internal
     */
    public function __construct($query, $units = 'imperial', $lang = 'en', $appid = '', $days, $cacheClass = false, $seconds = 600)
    {
        // Disable default error handling of SimpleXML (Do not throw E_WARNINGs).
        libxml_use_internal_errors(true);
        libxml_clear_errors();
        
        $owm = new OpenWeatherMap($cacheClass, $seconds);
        
        if ($days <= 5) {
            $answer = $owm->getRawHourlyForecastData($query, $units, $lang, $appid, 'xml');
        } else if ($days <= 14) {
            $answer = $owm->getRawDailyForecastData($query, $units, $lang, $appid, 'xml');
        } else {
            throw new \Exception('Error: forecasts are only available for the next 14 days.');
        }

        if ($answer === false) {
            // $query has the wrong format, throw error.
            throw new \Exception('Error: $query has the wrong format. See the documentation of OpenWeatherMap::getRawData() to read about valid formats.');
        }
        
        try {
            $xml = new \SimpleXMLElement($answer);
        } catch(\Exception $e) {
            // Invalid xml format. This happens in case OpenWeatherMap returns an error.
            // OpenWeatherMap always uses json for errors, even if one specifies xml as format.
            $error = json_decode($answer, true);
            if (isset($error['message'])) {
                throw new OWMException($error['message'], $error['cod']);
            } else {
                throw new OWMException('Unknown fatal error: OpenWeatherMap returned the following json object: ' . print_r($error));
            }
        }
        
        $this->city = new City(-1, $xml->location->name, $xml->location->location['longitude'], $xml->location->location['latitude'], $xml->location->country);
        $this->lastUpdate = new \DateTime($xml->meta->lastupdate);
        
        foreach($xml->forecast->time as $time) {
            $forecast = new WeatherForecast($time, $units, $lang);
            $forecast->city = $this->city;
            $this->forecasts[] = $forecast;
        }
    }

    /**
     * @internal
     */
    public function rewind() {
        $this->position = 0;
    }

    /**
     * @internal
     */
    public function current() {
        return $this->forecasts[$this->position];
    }

    /**
     * @internal
     */
    public function key() {
        return $this->position;
    }

    /**
     * @internal
     */
    public function next() {
        ++$this->position;
    }

    /**
     * @internal
     */
    public function valid() {
        return isset($this->forecasts[$this->position]);
    }
}
