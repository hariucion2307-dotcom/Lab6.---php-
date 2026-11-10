<?php 
require_once __DIR__ . '/RecipeValidator.php';
require_once __DIR__ . '/JsonStorage.php';
/**
 * Класс для обработки формы добавления рецептов.
 */
class RecipeForm
{
    /**
     * Валидатор данных рецепта.
     *
     * @var RecipeValidator
     */
    private RecipeValidator $validator;
    /**
     * Хранилище рецептов.
     *
     * @var JsonStorage
     */
    private JsonStorage $storage;
    /**
     * Создаёт объект формы.
     *
     * @param RecipeValidator $validator Валидатор рецептов.
     * @param JsonStorage $storage Хранилище рецептов.
     */
    public function __construct(
        RecipeValidator $validator,
        JsonStorage $storage
    ) {
        $this->validator = $validator;
        $this->storage = $storage;
    }
    /**
     * Обрабатывает данные, полученные из HTML-формы.
     *
     * @param array $data Данные из массива POST.
     * @return array Результат обработки формы.
     */
    public function handle(array $data): array
    {
        // Проверяем данные рецепта.
        $errors = $this->validator->validate($data);
        // Если есть ошибки, возвращаем их.
        if (!empty($errors)) {
            return [
                'success' => false,
                'errors' => $errors
            ];
        }
        // Создаём массив с данными нового рецепта.  
        $recipe = [
       'id' => time(),
       'title' => trim($data['title']),
       'ingredients' => trim($data['ingredients']),
       'instructions' => trim($data['instructions']),
       'category' => $data['category'],
       'difficulty' => $data['difficulty'],
       'prep_time' => (int) $data['prep_time'],
       'created_at' => $data['created_at'],
       'author' => trim($data['author'])
        ];
        // Сохраняем рецепт в JSON-файле.
        $saved = $this->storage->save($recipe);
        // Проверяем, удалось ли сохранить рецепт.
        if (!$saved) {
            return [
                'success' => false,
                'errors' => [
                    'storage' => 'Не удалось сохранить рецепт.'
                ]
            ];
        }
        // Возвращаем сообщение об успешном добавлении.
        return [
            'success' => true,
            'message' => 'Рецепт успешно добавлен!'
        ];
    }
}
