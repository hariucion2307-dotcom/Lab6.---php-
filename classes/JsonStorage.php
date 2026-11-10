<?php
/**
 * Класс для работы с JSON-файлом рецептов.
 *
 * Отвечает за чтение, сохранение и удаление рецептов.
 */
class JsonStorage
{
    /**
     * Путь к JSON-файлу.
     *
     * @var string
     */
    private string $filePath;
    /**
     * Создаёт хранилище рецептов.
     *
     * @param string|null $filePath Путь к JSON-файлу.
     */
    public function __construct(?string $filePath = null)
    {
        $this->filePath = $filePath ?? __DIR__ . '/../data/recipes.json';
    }
    /**
     * Получает все рецепты из JSON-файла.
     *
     * @return array Массив сохранённых рецептов.
     */
    public function getAll(): array
    {
        if (!file_exists($this->filePath)) {
            return [];
        }
        $content = file_get_contents($this->filePath);

        if ($content === false || trim($content) === '') {
            return [];
        }
        $recipes = json_decode($content, true);
        if (!is_array($recipes)) {
            return [];
        }
        return $recipes;
    }
    /**
     * Сохраняет новый рецепт.
     *
     * @param array $recipe Данные нового рецепта.
     * @return bool Результат сохранения.
     */
    public function save(array $recipe): bool
    {
        $recipes = $this->getAll();
        $recipes[] = $recipe;
        return $this->write($recipes);
    }
    /**
     * Записывает массив рецептов в JSON-файл.
     *
     * @param array $recipes Массив рецептов.
     * @return bool Результат записи.
     */
    private function write(array $recipes): bool
    {
        // Получение пути к папке с данными
        $directory = dirname($this->filePath);

        // Создание папки, если она отсутствует
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
                return false;
            }
        }
        // Преобразование массива рецептов в JSON
        $json = json_encode(
            $recipes,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        );
        if ($json === false) {
            return false;
        }
        // Сохранение данных в JSON-файл
        $result = file_put_contents(
            $this->filePath,
            $json,
            LOCK_EX
        );
        return $result !== false;
    }
    /**
     * Удаляет рецепт по его идентификатору.
     *
     * @param int $id Идентификатор рецепта.
     * @return bool Результат удаления.
     */
    public function delete(int $id): bool
    {
        $recipes = $this->getAll();
        $filteredRecipes = array_filter(
            $recipes,
            function ($recipe) use ($id) {
                return (int) ($recipe['id'] ?? 0) !== $id;
            }
        );
        if (count($filteredRecipes) === count($recipes)) {
            return false;
        }
        return $this->write(array_values($filteredRecipes));
    }
}

