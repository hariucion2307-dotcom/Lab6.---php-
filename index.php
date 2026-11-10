<?php
declare(strict_types=1);
session_start();
// Подключение классов приложения.
require_once __DIR__ . '/classes/ValidatorInterface.php';
require_once __DIR__ . '/classes/RecipeValidator.php';
require_once __DIR__ . '/classes/JsonStorage.php';
require_once __DIR__ . '/classes/RecipeForm.php';
// Создание объектов для работы с рецептами.
$storage = new JsonStorage();
$validator = new RecipeValidator();
$form = new RecipeForm($validator, $storage);
// Создание CSRF-токена.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
// Начальные значения.
$message = $_SESSION['success'] ?? '';
unset($_SESSION['success']);
$errors = [];
$formData = [];
// Обработка POST-запросов.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Проверка CSRF-токена.
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (
        !is_string($csrfToken) ||
        !hash_equals($_SESSION['csrf_token'], $csrfToken)
    ) {
        $errors['security'] = 'Ошибка безопасности. Обновите страницу.';
    } else {
        $action = $_POST['action'] ?? '';
        // Добавление нового рецепта.
        if ($action === 'add') {
            $formData = $_POST;
            // Передача данных в класс обработки формы.
            $result = $form->handle($_POST);
            if ($result['success']) {
                $_SESSION['success'] = $result['message'];
                // Обновление CSRF-токена.
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                // Перенаправление после успешного добавления.
                header('Location: index.php');
                exit;
            } else {
                $errors = $result['errors'] ?? [];
            }
        // Удаление рецепта.
        } elseif ($action === 'delete') {
            $id = filter_var(
                $_POST['id'] ?? '',
                FILTER_VALIDATE_INT
            );
            if ($id !== false && $id > 0 && $storage->delete($id)) {
                $_SESSION['success'] = 'Рецепт успешно удалён!';
            } else {
                $_SESSION['success'] = 'Не удалось удалить рецепт.';
            }
            header('Location: index.php');
            exit;
        } else {
            $errors['action'] = 'Неизвестное действие.';
        }
    }
}
// Получение всех рецептов.
$recipes = $storage->getAll();
// Получение поискового запроса.
$search = $_GET['search'] ?? '';
if (!is_string($search)) {
    $search = '';
}
$search = trim($search);
// Поиск по названию и ингредиентам.
if ($search !== '') {
    $recipes = array_filter(
        $recipes,
        function (array $recipe) use ($search): bool {
            $text = ($recipe['title'] ?? '') . ' ' .
                    ($recipe['ingredients'] ?? '');
            if (function_exists('mb_stripos')) {
                return mb_stripos($text, $search) !== false;
            }
            return stripos($text, $search) !== false;
        }
    );
}
// Параметры сортировки.
$sort = $_GET['sort'] ?? 'created_at';
$order = $_GET['order'] ?? 'desc';
// Разрешённые поля сортировки.
$allowedSortFields = [
    'title',
    'category',
    'difficulty',
    'prep_time',
    'created_at',
    'author'
];
// Проверка поля сортировки.
if (!is_string($sort) || !in_array($sort, $allowedSortFields, true)) {
    $sort = 'created_at';
}
// Проверка порядка сортировки.
if (!is_string($order) || !in_array($order, ['asc', 'desc'], true)) {
    $order = 'desc';
}
// Сортировка рецептов.
usort(
    $recipes,
    function (array $a, array $b) use ($sort, $order): int {
        $valueA = $a[$sort] ?? '';
        $valueB = $b[$sort] ?? '';
        if ($sort === 'prep_time') {
            $comparison = (int)$valueA <=> (int)$valueB;
        } else {
            $comparison = strnatcasecmp(
                (string)$valueA,
                (string)$valueB
            );
        }
        return $order === 'asc' ? $comparison : -$comparison;
    }
);
/**
 * Безопасно преобразует значение для вывода в HTML.
 *
 * @param mixed $value Значение для преобразования.
 * @return string Безопасная HTML-строка.
 */
function escapeHtml(mixed $value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}
// Списки категорий и сложности.
$categories = ['Завтрак', 'Обед', 'Ужин', 'Десерт'];
$difficulties = ['Лёгкая', 'Средняя', 'Сложная'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Менеджер рецептов</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <header class="page-header">
        <h1>Менеджер рецептов</h1>
        <p>Добавляйте, сохраняйте и находите свои любимые рецепты.</p>
    </header>
    <main>
        <!-- Сообщение об успешном выполнении -->
        <?php if ($message !== ''): ?>
            <div class="success-message">
                <?= escapeHtml($message) ?>
            </div>
        <?php endif; ?>
        <!-- Общие ошибки -->
        <?php if (!empty($errors)): ?>
            <?php foreach (['security', 'storage', 'action'] as $errorKey): ?>
                <?php if (isset($errors[$errorKey])): ?>
                    <div class="error-message">
                        <?= escapeHtml($errors[$errorKey]) ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
        <!-- Форма добавления рецепта -->
        <section class="form-section">
            <h2>Добавить новый рецепт</h2>
            <form method="POST" action="index.php">
                <input type="hidden" name="action" value="add">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= escapeHtml($_SESSION['csrf_token']) ?>"
                >
                <!-- Название рецепта -->
                <div class="form-group">
                    <label for="title">Название рецепта:</label>
                    <input
                        type="text"
                        id="title"
                        name="title"
                        minlength="3"
                        maxlength="100"
                        required
                        value="<?= escapeHtml($formData['title'] ?? '') ?>"
                        placeholder="Например: Пицца Маргарита">
                    <?php if (isset($errors['title'])): ?>
                        <p class="field-error">
                            <?= escapeHtml($errors['title']) ?>
                        </p>
                    <?php endif; ?>
                </div>
                <!-- Ингредиенты -->
                <div class="form-group">
                    <label for="ingredients">Ингредиенты:</label>
                    <textarea
                        id="ingredients"
                        name="ingredients"
                        maxlength="2000"
                        required
                        placeholder="Укажите необходимые ингредиенты"
                    ><?= escapeHtml($formData['ingredients'] ?? '') ?></textarea>
                    <?php if (isset($errors['ingredients'])): ?>
                        <p class="field-error">
                            <?= escapeHtml($errors['ingredients']) ?>
                        </p>
                    <?php endif; ?>
                </div>
                <!-- Способ приготовления -->
                <div class="form-group">
                    <label for="instructions">Способ приготовления:</label>
                    <textarea
                        id="instructions"
                        name="instructions"
                        maxlength="5000"
                        required
                        placeholder="Опишите процесс приготовления"
                    ><?= escapeHtml($formData['instructions'] ?? '') ?></textarea>
                    <?php if (isset($errors['instructions'])): ?>
                        <p class="field-error">
                            <?= escapeHtml($errors['instructions']) ?>
                        </p>
                    <?php endif; ?>
                </div>
                <!-- Категория -->
                <div class="form-group">
                    <label for="category">Категория:</label>
                    <select id="category" name="category" required>
                        <option value="">Выберите категорию</option>
                        <?php foreach ($categories as $category): ?>
                            <option
                                value="<?= escapeHtml($category) ?>"
                                <?= ($formData['category'] ?? '') === $category ? 'selected' : '' ?>>
                                <?= escapeHtml($category) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['category'])): ?>
                        <p class="field-error">
                            <?= escapeHtml($errors['category']) ?>
                        </p>
                    <?php endif; ?>
                </div>
                <!-- Сложность приготовления -->
                <div class="form-group">
                    <label for="difficulty">Сложность приготовления:</label>
                    <select id="difficulty" name="difficulty" required>
                        <option value="">Выберите сложность</option>
                        <?php foreach ($difficulties as $difficulty): ?>
                            <option
                                value="<?= escapeHtml($difficulty) ?>"
                                <?= ($formData['difficulty'] ?? '') === $difficulty ? 'selected' : '' ?>>
                                <?= escapeHtml($difficulty) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['difficulty'])): ?>
                        <p class="field-error">
                            <?= escapeHtml($errors['difficulty']) ?>
                        </p>
                    <?php endif; ?>
                </div>
                <!-- Время приготовления -->
                <div class="form-group">
                    <label for="prep_time">
                        Время приготовления (минуты):
                    </label>
                    <input
                        type="number"
                        id="prep_time"
                        name="prep_time"
                        min="1"
                        max="1440"
                        required
                        value="<?= escapeHtml($formData['prep_time'] ?? '') ?>"
                        placeholder="Например: 40">
                    <?php if (isset($errors['prep_time'])): ?>
                        <p class="field-error">
                            <?= escapeHtml($errors['prep_time']) ?>
                        </p>
                    <?php endif; ?>
                </div>
                <!-- Дата создания рецепта -->
                <div class="form-group">
                    <label for="created_at">Дата создания:</label>
                    <input
                        type="date"
                        id="created_at"
                        name="created_at"
                        required
                        value="<?= escapeHtml($formData['created_at'] ?? date('Y-m-d')) ?>"
                    >
                    <?php if (isset($errors['created_at'])): ?>
                        <p class="field-error">
                            <?= escapeHtml($errors['created_at']) ?>
                        </p>
                    <?php endif; ?>
                </div>
                <!-- Автор рецепта -->
                <div class="form-group">
                    <label for="author">Автор рецепта:</label>
                    <input
                        type="text"
                        id="author"
                        name="author"
                        minlength="2"
                        maxlength="100"
                        required
                        value="<?= escapeHtml($formData['author'] ?? '') ?>"
                        placeholder="Введите имя автора"
                    >
                    <?php if (isset($errors['author'])): ?>
                        <p class="field-error">
                            <?= escapeHtml($errors['author']) ?>
                        </p>
                    <?php endif; ?>
                </div>
                <button type="submit" class="add-button">Добавить рецепт</button>
            </form>
        </section>
        <!-- Поиск рецептов -->
        <section class="search-section">
            <h2>Поиск рецептов</h2>
            <form method="GET" action="index.php" class="search-form">
                <input
                    type="text"
                    name="search"
                    placeholder="Название или ингредиент..."
                    value="<?= escapeHtml($search) ?>">
                <input type="hidden" name="sort" value="<?= escapeHtml($sort) ?>">
                <input type="hidden" name="order" value="<?= escapeHtml($order) ?>">
                <button type="submit">Найти</button>
                <a href="index.php" class="reset-button">Сбросить</a>
            </form>
        </section>
        <!-- Список рецептов -->
        <section class="recipes-section">
            <h2>Сохранённые рецепты</h2>
            <!-- Форма сортировки -->
            <form method="GET" action="index.php" class="sort-form">
                <input
                    type="hidden"
                    name="search"
                    value="<?= escapeHtml($search) ?>"
                >
                <label for="sort">Сортировать по:</label>
                <select name="sort" id="sort">
                    <option value="created_at" <?= $sort === 'created_at' ? 'selected' : '' ?>>Дате добавления</option>
                    <option value="title" <?= $sort === 'title' ? 'selected' : '' ?>>Названию</option>
                    <option value="category" <?= $sort === 'category' ? 'selected' : '' ?>>Категории</option>
                    <option value="difficulty" <?= $sort === 'difficulty' ? 'selected' : '' ?>>Сложности</option>
                    <option value="prep_time" <?= $sort === 'prep_time' ? 'selected' : '' ?>>Времени приготовления</option>
                    <option value="author" <?= $sort === 'author' ? 'selected' : '' ?>>Автору</option>
                </select>
                <label for="order">Порядок:</label>
                <select name="order" id="order">
                    <option value="asc" <?= $order === 'asc' ? 'selected' : '' ?>>По возрастанию</option>
                    <option value="desc" <?= $order === 'desc' ? 'selected' : '' ?>>По убыванию</option>
                </select>
                <button type="submit">Сортировать</button>
            </form>
            <?php if (empty($recipes)): ?>
                <p class="empty-message">
                    Рецепты не найдены. Добавьте свой первый рецепт!
                </p>
            <?php else: ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Название</th>
                                <th>Категория</th>
                                <th>Сложность</th>
                                <th>Время (мин.)</th>
                                <th>Ингредиенты</th>
                                <th>Приготовление</th>
                                <th>Дата добавления</th>
                                <th>Автор</th>
                                <th>Действие</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recipes as $recipe): ?>
                                <tr>
                                    <td>
                                        <?= escapeHtml($recipe['title'] ?? '') ?>
                                    </td>
                                    <td>
                                        <?= escapeHtml($recipe['category'] ?? '') ?>
                                    </td>
                                    <td>
                                        <?= escapeHtml($recipe['difficulty'] ?? '') ?>
                                    </td>
                                    <td>
                                        <?= (int)($recipe['prep_time'] ?? 0) ?>
                                    </td>
                                    <td>
                                        <?= nl2br(escapeHtml($recipe['ingredients'] ?? '')) ?>
                                    </td>
                                    <td>
                                        <?= nl2br(escapeHtml($recipe['instructions'] ?? '')) ?>
                                    </td>
                                    <td>
                                        <?= escapeHtml($recipe['created_at'] ?? '') ?>
                                    </td>
                                    <td>
                                        <?= escapeHtml($recipe['author'] ?? '') ?>
                                    </td>
                                    <td>
                                        <form method="POST" action="index.php">
                                            <input
                                                type="hidden"
                                                name="action"
                                                value="delete"
                                            >
                                            <input 
                                                type="hidden" 
                                                name="csrf_token" 
                                                value="<?= escapeHtml($_SESSION['csrf_token']) ?>">
                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int)($recipe['id'] ?? 0) ?>">
                                            <button type="submit" class="delete-button">Удалить</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="total-count">
                    Всего рецептов: <?= count($recipes) ?>
                </p>
            <?php endif; ?>
        </section>
    </main>
    <footer>
        <p>Менеджер рецептов | Лабораторная работа №6</p>
    </footer>
</div>
</body>
</html>

