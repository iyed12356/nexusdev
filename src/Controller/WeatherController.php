<?php

namespace App\Controller;

use App\Service\WeatherService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/weather')]
class WeatherController extends AbstractController
{
    #[Route('/widget', name: 'app_weather_widget', methods: ['GET'])]
    public function widget(WeatherService $weatherService): Response
    {
        // Default to a major city or get from user preference
        $weather = $weatherService->getCurrentWeather('Paris');
        
        return $this->render('weather/widget.html.twig', [
            'weather' => $weather,
        ]);
    }

    #[Route('/api/{city}', name: 'app_weather_api', methods: ['GET'])]
    public function api(string $city, WeatherService $weatherService): JsonResponse
    {
        $weather = $weatherService->getCurrentWeather($city);
        
        if (!$weather) {
            return new JsonResponse(['error' => 'Weather data not available'], 404);
        }
        
        return new JsonResponse([
            'city' => $weather['name'],
            'temperature' => $weather['main']['temp'],
            'feels_like' => $weather['main']['feels_like'],
            'humidity' => $weather['main']['humidity'],
            'description' => $weather['weather'][0]['description'],
            'icon' => $weatherService->getWeatherIconUrl($weather['weather'][0]['icon']),
            'wind_speed' => $weather['wind']['speed'],
        ]);
    }
}
