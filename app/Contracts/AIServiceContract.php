<?php

namespace App\Contracts;

interface AIServiceContract
{
    public function generateDailyPlan(string $todoList, array $userPreferences = []);
}
