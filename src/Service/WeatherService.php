<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class WeatherService
{
    private HttpClientInterface $httpClient;
    private string $apiKey;

    public function __construct(HttpClientInterface $httpClient, string $apiKey = '')
    {
        $this->httpClient = $httpClient;
        $this->apiKey = $apiKey;
    }

    public function getCurrentWeather(string $city): ?array
    {
        if (empty($this->apiKey)) {
            // Return mock data if no API key
            return $this->getMockWeatherData($city);
        }

        try {
            $response = $this->httpClient->request(
                'GET',
                'https://api.openweathermap.org/data/2.5/weather',
                [
                    'query' => [
                        'q' => $city,
                        'appid' => $this->apiKey,
                        'units' => 'metric',
                    ],
                ]
            );

            return $response->toArray();
        } catch (\Exception $e) {
            return $this->getMockWeatherData($city);
        }
    }

    public function getForecast(string $city, int $days = 5): ?array
    {
        if (empty($this->apiKey)) {
            return null;
        }

        try {
            $response = $this->httpClient->request(
                'GET',
                'https://api.openweathermap.org/data/2.5/forecast',
                [
                    'query' => [
                        'q' => $city,
                        'appid' => $this->apiKey,
                        'units' => 'metric',
                        'cnt' => $days * 8, // 3-hour intervals
                    ],
                ]
            );

            return $response->toArray();
        } catch (\Exception $e) {
            return null;
        }
    }

    private function getMockWeatherData(string $city): array
    {
        return [
            'name' => $city,
            'main' => [
                'temp' => 22,
                'feels_like' => 21,
                'humidity' => 65,
                'pressure' => 1013,
            ],
            'weather' => [
                [
                    'main' => 'Clear',
                    'description' => 'clear sky',
                    'icon' => '01d',
                ],
            ],
            'wind' => [
                'speed' => 3.5,
            ],
        ];
    }

    public function getWeatherIconUrl(string $iconCode): string
    {
        return sprintf('https://openweathermap.org/img/wn/%s@2x.png', $iconCode);
    }
}
