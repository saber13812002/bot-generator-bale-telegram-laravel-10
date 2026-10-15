<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WeatherHistory extends Model
{
    use HasFactory;

    /**
     * The migration 2026_01_24_035132_create_weather_history_table creates the
     * table with the singular name "weather_history", while Eloquent would
     * default to "weather_histories". Pin the table name explicitly so the
     * model queries the table that actually exists in the database.
     */
    protected $table = 'weather_history';

    protected $fillable = [
        'latitude',
        'longitude',
        'date',
        'weather_data',
        'temperature',
        'precipitation',
        'wind_speed',
        'wind_gust',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'date' => 'date',
        'weather_data' => 'array',
        'temperature' => 'decimal:2',
        'precipitation' => 'decimal:2',
        'wind_speed' => 'decimal:2',
        'wind_gust' => 'decimal:2',
    ];

    /**
     * دریافت داده‌های دیروز برای یک location
     */
    public static function getYesterdayWeather(float $latitude, float $longitude): ?self
    {
        $yesterday = now()->subDay()->toDateString();
        
        return self::where('latitude', $latitude)
            ->where('longitude', $longitude)
            ->where('date', $yesterday)
            ->first();
    }

    /**
     * ذخیره داده‌های آب و هوا
     */
    public static function saveWeatherData(float $latitude, float $longitude, array $weatherData, string $date = null): self
    {
        $date = $date ?? now()->toDateString();
        
        return self::updateOrCreate(
            [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'date' => $date,
            ],
            [
                'weather_data' => $weatherData,
                'temperature' => $weatherData['temperature'] ?? null,
                'precipitation' => $weatherData['precipitation'] ?? $weatherData['rainIntensity'] ?? null,
                'wind_speed' => $weatherData['windSpeed'] ?? null,
                'wind_gust' => $weatherData['windGust'] ?? null,
            ]
        );
    }
}
