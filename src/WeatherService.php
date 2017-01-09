<?php
namespace Drupal\openweather;

use Drupal\Component\Utility\Html;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * WeatherService.
 */
class WeatherService {

  /**
   * Get a complete query for the API.
   */
  public function createRequest($options) {
    $query = [];
    $my_config = \Drupal::config('openweather.settings')->get('appid');
    $query['appid'] = $my_config;
    $query['cnt'] = $options['count'];
    $input_data = Html::escape($options['input_value']);
    switch ($options['input_options']) {
      case 'city_id':
        $query['id'] = $input_data;
        break;

      case 'city_name':
        $query['q'] = $input_data;
        break;

      case 'geo_coord':
        $pieces = explode(",", $input_data);
        $query['lat'] = $pieces[0];
        $query['lon'] = $pieces[1];
        break;

      case 'zip_code':
        $query['zip'] = $input_data;
        break;
    }
    // kint($query);
    return $query;

  }

  /**
   * Return the data from the API in xml format.
   */
  public function getWeatherInformation($options) {
    try {
      $client = new Client(['base_uri' => 'http://api.openweathermap.org/']);
      switch ($options['display_type']) {
        case 'current_details':
          $response = $client->request('GET', '/data/2.5/weather',
          [
            'query' => $this->createRequest($options),
          ]);
          break;

        case 'forecast_hourly':
          $response = $client->request('GET', '/data/2.5/forecast',
          [
            'query' => $this->createRequest($options),
          ]);
          break;

        case 'forecast_daily':
          $response = $client->request('GET', '/data/2.5/forecast/daily',
          [
            'query' => $this->createRequest($options),
          ]);
          break;
      }

    }
    catch (GuzzleException $e) {
      watchdog_exception('openweather', $e);
      return FALSE;
    }
    $data = $response->getBody()->getContents();
    return $data;
  }

  /**
   * Return an array containing the current weather information.
   */
  public function getCurrentWeatherInformation($output, $config) {
    $dependency = NULL;
    foreach ($config['outputitems'] as $key => $value) {
      if (!empty($config['outputitems'][$value])) {
        switch ($config['outputitems'][$value]) {
          case 'humidity':
            $html[$value] = $output['main']['humidity'] . '%';
            break;

          case 'temp_max':
            $html[$value] = round($output['main']['temp_max'] - 273.15, 2) . '°C';
            break;

          case 'temp_min':
            $html[$value] = round($output['main']['temp_min'] - 273.15, 2) . '°C';
            break;

          case 'name':
            $html[$value] = $output['name'];
            break;

          case 'date':
            $html[$value] = gmstrftime("%B %d %Y", REQUEST_TIME);
            break;

          case 'coord':
            $html[$value]['lon'] = $output['coord']['lon'];
            $html[$value]['lat'] = $output['coord']['lat'];
            break;

          case 'weather':
            $html[$value]['desc'] = $output['weather'][0]['main'];
            $html[$value]['image'] = $output['weather'][0]['icon'];
            break;

          case 'temp':
            $html[$value] = round($output['main']['temp'] - 273.15) . '°C';
            break;

          case 'pressure':
            $html[$value] = $output['main']['pressure'];
            break;

          case 'sea_level':
            $html[$value] = $output['main']['sea_level'];
            break;

          case 'grnd_level':
            $html[$value] = $output['main']['grnd_level'];
            break;

          case 'wind_speed':
            $html[$value] = round($output['wind']['speed'] * (60 * 60 / 1000), 1) . 'km/h';
            break;

          case 'wind_deg':
            $html[$value] = $output['wind']['deg'];
            break;

          case 'time':
            $dependency = $current_time;
            $html[$value] = date("g:i a", REQUEST_TIME);
            break;

          case 'day':
            $html[$value] = gmstrftime("%A", REQUEST_TIME);
            break;

          case 'country':
            $html[$value] = $output['sys']['country'];
            break;

          case 'sunrise':
            $html[$value] = date("g:i a", $output['sys']['sunrise']);
            break;

          case 'sunset':
            $html[$value] = date("g:i a", $output['sys']['sunset']);
            break;
        }
      }
    }
    $build[] = [
      '#theme' => 'openweather',
      '#openweather_detail' => $html,
      '#attached' => array(
        'library' => array(
          'openweather/openweather_theme',
        ),
      ),
      '#cache' => array('max-age' => 0),
    ];
    return $build;
  }

  /**
   * Return an array containing the forecast weather info with 3 hoursinterval.
   */
  public function getHourlyForecastWeatherInformation($output, $config) {
    foreach ($output['list'] as $key => $data) {
      $html[$key]['forecast_time'] = date("g:i a", strtotime($output['list'][$key]['dt_txt']));
      $html[$key]['forecast_date'] = gmstrftime("%B %d", $output['list'][$key]['dt']);
      foreach ($config['outputitems'] as $optionkey => $value) {
        if (!empty($config['outputitems'][$value])) {
          switch ($config['outputitems'][$value]) {
            case 'humidity':
              $html[$key][$value] = $output['list'][$key]['main']['humidity'] . '%';
              break;

            case 'temp_max':
              $html[$key][$value] = round($output['list'][$key]['main']['temp_max'] - 273.15, 2) . '°C';
              break;

            case 'temp_min':
              $html[$key][$value] = round($output['list'][$key]['main']['temp_min'] - 273.15, 2) . '°C';
              break;

            case 'name':
              $html[$key][$value] = $output['city']['name'];
              break;

            case 'date':
              $html[$key][$value] = gmstrftime("%B %d %Y", REQUEST_TIME);
              break;

            case 'coord':
              $html[$key][$value]['lon'] = $output['city']['coord']['lon'];
              $html[$key][$value]['lat'] = $output['city']['coord']['lat'];
              break;

            case 'weather':
              $html[$key][$value]['desc'] = $output['list'][$key]['weather'][0]['main'];
              $html[$key][$value]['image'] = $output['list'][$key]['weather'][0]['icon'];
              break;

            case 'temp':
              $html[$key][$value] = round($output['list'][$key]['main']['temp'] - 273.15) . '°C';
              break;

            case 'pressure':
              $html[$key][$value] = $output['list'][$key]['main']['pressure'];
              break;

            case 'sea_level':
              $html[$key][$value] = $output['list'][$key]['main']['sea_level'];
              break;

            case 'grnd_level':
              $html[$key][$value] = $output['list'][$key]['main']['grnd_level'];
              break;

            case 'wind_speed':
              $html[$key][$value] = round($output['list'][$key]['wind']['speed'] * (60 * 60 / 1000), 1) . 'km/h';
              break;

            case 'wind_deg':
              $html[$key][$value] = $output['list'][$key]['wind']['deg'];
              break;

            case 'time':
              $dependency = $current_time;
              $html[$key][$value] = date("g:i a", REQUEST_TIME);
              break;

            case 'day':
              $html[$key][$value] = gmstrftime("%A", $output['list'][$key]['dt']);;
              break;

            case 'country':
              $html[$key][$value] = $output['city']['country'];
              break;
          }
        }
      }
    }
    $build[] = [
      '#theme' => 'openweather_hourlyforecast',
      '#hourlyforecast_detail' => $html,
      '#attached' => array(
        'library' => array(
          'openweather/openweatherhourlyforecast_theme',
        ),
      ),
      '#cache' => array('max-age' => 0),
    ];
    return $build;
  }

}