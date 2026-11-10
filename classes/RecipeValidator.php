<?php
require_once __DIR__ . '/ValidatorInterface.php';
/**
 * Класс для проверки данных рецепта.
 */
class RecipeValidator implements ValidatorInterface
{
    /**
     * Проверяет данные нового рецепта.
     *
     * @param array $data Данные, полученные из HTML-формы.
     * @return array Массив обнаруженных ошибок.
     */
    public function validate(array $data): array
    {
        $errors = [];
        // Проверка названия рецепта
        $title = trim($data['title'] ?? '');
        if ($title === '') {
            $errors['title'] = 'Введите название рецепта.';
        } elseif (strlen($title) < 3 || strlen($title) > 100) {
            $errors['title'] = 'Название должно содержать от 3 до 100 символов.';
        }
        // Проверка ингредиентов
        if (empty(trim($data['ingredients'] ?? ''))) {
            $errors['ingredients'] = 'Введите ингредиенты.';
        }
        // Проверка способа приготовления
        if (empty(trim($data['instructions'] ?? ''))) {
            $errors['instructions'] = 'Введите способ приготовления.';
        }
        // Проверка категории
        $categories = ['Завтрак', 'Обед', 'Ужин', 'Десерт'];

        if (!in_array($data['category'] ?? '', $categories, true)) {
            $errors['category'] = 'Выберите правильную категорию.';
        }
        // Проверка сложности приготовления
        $difficulties = ['Лёгкая', 'Средняя', 'Сложная'];

        if (!in_array($data['difficulty'] ?? '', $difficulties, true)) {
            $errors['difficulty'] = 'Выберите сложность приготовления.';
        }
        // Проверка времени приготовления
        $prepTime = filter_var($data['prep_time'] ?? '', FILTER_VALIDATE_INT);
        if ($prepTime === false || $prepTime < 1 || $prepTime > 1440) {
            $errors['prep_time'] = 'Время должно быть от 1 до 1440 минут.';
        }
        // Проверка даты создания рецепта
        $createdAt = $data['created_at'] ?? '';
        $date = DateTime::createFromFormat('!Y-m-d', $createdAt);
        if (!$date || $date->format('Y-m-d') !== $createdAt) {
            $errors['created_at'] = 'Укажите правильную дату создания рецепта.';
        }
        // Проверка автора рецепта
        $author = trim($data['author'] ?? '');
        if ($author === '') {
            $errors['author'] = 'Введите имя автора.';
        } elseif (strlen($author) < 2 || strlen($author) > 100) {
            $errors['author'] = 'Имя автора должно содержать от 2 до 100 символов.';
        }
        return $errors;
    }
}


