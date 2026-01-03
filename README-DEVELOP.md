# How to start developing bot

## for example

 - all the things started with route.php
    ```php
    Route::post('/webhook-weather', [WeatherController::class, 'index']);
    ```
 - next step is definitely easy WeatherController::index()
 - you should select index file in our implemented index something that similar to you project
 - for example in this example i want to have bot that call api then send message to peaple that messeage to us 
 - if this user has get the mission he should send us the resulst or report 
 - we send this report to another api
 - done

