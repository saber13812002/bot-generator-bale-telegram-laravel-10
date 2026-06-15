<?php

namespace App\Http\Controllers;

use App\Helpers\BotHelper;
use App\Interfaces\Repositories\LibraryRepository;
use App\Interfaces\Services\BookLibraryDeliveryService;
use App\Interfaces\Services\BookLibraryPlanService;
use App\Interfaces\Services\BookLibraryService;
use App\Models\Bot;
use App\Models\BotUsers;
use App\Models\LibraryUserBook;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Telegram;

class BookLibraryController extends Controller
{
    public function __construct(
        private LibraryRepository $libraryRepository,
        private BookLibraryService $bookLibraryService,
        private BookLibraryDeliveryService $deliveryService,
        private BookLibraryPlanService $planService
    ) {}

    public function webhook(Request $request): int
    {
        Log::info('📚 [BookLibrary] Webhook received');

        try {
            if (!$request->has('origin')) {
                return 200;
            }

            $type = $request->input('origin');
            $botMotherId = (int) $request->input('bot_mother_id', 1);
            $botId = $request->input('bot_id') ? (int) $request->input('bot_id') : null;

            $bot = $this->createBotInstance($request, $type, $botId);
            if (!$bot) {
                Log::error('❌ [BookLibrary] Could not create bot instance');
                return 200;
            }

            Log::info('📥 [BookLibrary] Request details', [
                'type' => $type,
                'bot_id' => $botId,
                'bot_mother_id' => $botMotherId,
            ]);

            $update = $request->json()->all() ?? $request->all();
            $chatId = $bot->ChatID();

            if (isset($update['callback_query'])) {
                $this->handleCallbackQuery($bot, $update['callback_query'], $chatId, $type, $botMotherId, $botId);
                return 200;
            }

            $text = $bot->Text() ?? '';
            if ($text !== '') {
                $this->handleTextMessage($bot, trim($text), $chatId, $type, $botMotherId, $botId);
            }

            return 200;
        } catch (Exception $e) {
            Log::error('❌ [BookLibrary] Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 500;
        }
    }

    private function createBotInstance(Request $request, string $type, ?int $botId): ?Telegram
    {
        $token = null;

        if ($request->has('token')) {
            $token = $request->input('token');
        } elseif ($botId) {
            $botModel = Bot::find($botId);
            if ($botModel) {
                $token = $type === 'bale' ? $botModel->bale_bot_token : $botModel->telegram_bot_token;
            }
        }

        if (!$token) {
            return null;
        }

        return $type === 'bale' ? new Telegram($token, 'bale') : new Telegram($token);
    }

    private function handleTextMessage(
        Telegram $bot,
        string $text,
        int $chatId,
        string $type,
        int $botMotherId,
        ?int $botId
    ): void {
        $botUser = BotUsers::firstOrNew((string) $chatId, $botMotherId, $type);
        $instanceBotId = $botId ?? (int) $botUser->setting('library_bot_instance_id', 0);

        if (!$instanceBotId && $botId) {
            $botUser->settings(['library_bot_instance_id' => $botId]);
            $instanceBotId = $botId;
        }

        if (str_starts_with($text, '/start')) {
            if ($botId) {
                $botUser->settings(['library_bot_instance_id' => $botId]);
            }
            $this->showWelcome($bot, $botUser);
            return;
        }

        if (in_array(mb_strtolower($text), ['/help', 'help', 'راهنما', '/راهنما'], true)) {
            BotHelper::sendMessage($bot, trans('book_library.main_help'));
            $this->sendMainMenu($bot);
            return;
        }

        if (!$instanceBotId) {
            BotHelper::sendMessage($bot, trans('book_library.delivery_error'));
            return;
        }

        if ($text === trans('book_library.menu_intro')) {
            $this->showGenrePage($bot, $botUser, $instanceBotId, 1);
            return;
        }

        if ($text === trans('book_library.menu_random')) {
            $this->deliverRandomBook($bot, $botUser, $instanceBotId, $type, null);
            return;
        }

        if ($text === trans('book_library.menu_my_books')) {
            $this->showMyBooks($bot, $botUser, $instanceBotId);
            return;
        }

        if ($text === trans('book_library.menu_upgrade')) {
            $this->showPlanMenu($bot, $botUser, $instanceBotId);
            return;
        }

        BotHelper::sendMessage($bot, trans('book_library.use_menu'));
    }

    private function handleCallbackQuery(
        Telegram $bot,
        array $callbackQuery,
        int $chatId,
        string $type,
        int $botMotherId,
        ?int $botId
    ): void {
        $callbackData = $callbackQuery['data'] ?? '';
        $botUser = BotUsers::firstOrNew((string) $chatId, $botMotherId, $type);
        $instanceBotId = $botId ?? (int) $botUser->setting('library_bot_instance_id', 0);

        $callbackQueryId = $callbackQuery['id'] ?? null;
        if ($callbackQueryId) {
            $bot->answerCallbackQuery(['callback_query_id' => $callbackQueryId]);
        }

        if (str_starts_with($callbackData, 'bl:genre_page:')) {
            $page = (int) str_replace('bl:genre_page:', '', $callbackData);
            $this->showGenrePage($bot, $botUser, $instanceBotId, $page);
            return;
        }

        if (str_starts_with($callbackData, 'bl:genre:')) {
            $genreId = (int) str_replace('bl:genre:', '', $callbackData);
            $botUser->settings(['library_selected_genre_id' => $genreId]);
            $this->showBookPage($bot, $botUser, $instanceBotId, $genreId, 1);
            return;
        }

        if (str_starts_with($callbackData, 'bl:books_page:')) {
            $parts = explode(':', $callbackData);
            $genreId = (int) ($parts[2] ?? 0);
            $page = (int) ($parts[3] ?? 1);
            $this->showBookPage($bot, $botUser, $instanceBotId, $genreId, $page);
            return;
        }

        if (str_starts_with($callbackData, 'bl:book:')) {
            $bookId = (int) str_replace('bl:book:', '', $callbackData);
            $this->deliverSelectedBook($bot, $botUser, $instanceBotId, $type, $bookId);
            return;
        }

        if (str_starts_with($callbackData, 'bl:random:')) {
            $genreId = (int) str_replace('bl:random:', '', $callbackData);
            $genreId = $genreId > 0 ? $genreId : null;
            $this->deliverRandomBook($bot, $botUser, $instanceBotId, $type, $genreId);
            return;
        }

        if (str_starts_with($callbackData, 'bl:qa:')) {
            $parts = explode(':', $callbackData);
            $action = $parts[2] ?? '';
            $userBookId = (int) ($parts[3] ?? 0);
            $userBook = LibraryUserBook::where('id', $userBookId)
                ->where('bot_user_id', $botUser->id)
                ->first();
            if ($userBook) {
                $this->deliveryService->sendQuickAction($bot, $botUser, $userBook, $instanceBotId, $type, $action);
            }
            return;
        }

        if (str_starts_with($callbackData, 'bl:plan:')) {
            $plan = str_replace('bl:plan:', '', $callbackData);
            $identifier = $botUser->chat_id . ' (' . $type . ')';
            $result = $this->planService->requestPlanUpgrade($botUser, $instanceBotId, $plan, $identifier);
            BotHelper::sendMessage($bot, $result['message']);
            return;
        }
    }

    private function showWelcome(Telegram $bot, BotUsers $botUser): void
    {
        BotHelper::sendMessage($bot, trans('book_library.welcome'));
        $this->sendMainMenu($bot);
    }

    private function sendMainMenu(Telegram $bot): void
    {
        $keyboard = [
            [trans('book_library.menu_intro')],
            [trans('book_library.menu_random')],
            [trans('book_library.menu_my_books')],
            [trans('book_library.menu_upgrade')],
        ];
        $replyKeyboard = $bot->buildKeyBoard($keyboard, true, true);
        BotHelper::sendKeyboardMessage($bot, trans('book_library.menu_prompt'), $replyKeyboard);
    }

    private function showGenrePage(Telegram $bot, BotUsers $botUser, int $botId, int $page): void
    {
        $genres = $this->libraryRepository->getGenresForPage($botId, $page);
        if ($genres->isEmpty()) {
            BotHelper::sendMessage($bot, trans('book_library.no_genres'));
            return;
        }

        $keyboard = [];
        foreach ($genres as $genre) {
            $keyboard[] = [$bot->buildInlineKeyBoardButton($genre->name, callback_data: "bl:genre:{$genre->id}")];
        }

        $maxPage = $this->libraryRepository->getMaxGenrePage($botId);
        if ($page < $maxPage) {
            $keyboard[] = [$bot->buildInlineKeyBoardButton(trans('book_library.genre_next'), callback_data: 'bl:genre_page:' . ($page + 1))];
        }

        $inlineKeyboard = $bot->buildInlineKeyBoard($keyboard);
        BotHelper::sendKeyboardMessage($bot, trans('book_library.genre_prompt'), $inlineKeyboard);
    }

    private function showBookPage(Telegram $bot, BotUsers $botUser, int $botId, int $genreId, int $page): void
    {
        $genre = $this->libraryRepository->findGenre($genreId, $botId);
        if (!$genre) {
            BotHelper::sendMessage($bot, trans('book_library.genre_not_found'));
            return;
        }

        $perPage = config('book_library.books_per_page', 4);
        $books = $this->libraryRepository->getBooksForGenre($botId, $genreId, $page, $perPage);
        $total = $this->libraryRepository->countBooksForGenre($botId, $genreId);

        if ($books->isEmpty()) {
            BotHelper::sendMessage($bot, trans('book_library.no_books'));
            return;
        }

        $keyboard = [];
        foreach ($books as $book) {
            $keyboard[] = [$bot->buildInlineKeyBoardButton('📘 ' . $book->title, callback_data: "bl:book:{$book->id}")];
        }

        $maxPage = (int) ceil($total / $perPage);
        if ($page < $maxPage) {
            $keyboard[] = [$bot->buildInlineKeyBoardButton(trans('book_library.books_next'), callback_data: "bl:books_page:{$genreId}:" . ($page + 1))];
        }

        $keyboard[] = [$bot->buildInlineKeyBoardButton(trans('book_library.random_pick'), callback_data: "bl:random:{$genreId}")];

        $inlineKeyboard = $bot->buildInlineKeyBoard($keyboard);
        $message = trans('book_library.books_prompt', ['genre' => $genre->name]);
        BotHelper::sendKeyboardMessage($bot, $message, $inlineKeyboard);
    }

    private function deliverSelectedBook(Telegram $bot, BotUsers $botUser, int $botId, string $type, int $bookId): void
    {
        if (!$this->planService->canDeliver($botUser, $botId)) {
            BotHelper::sendMessage($bot, trans('book_library.quota_exceeded'));
            $this->showPlanMenu($bot, $botUser, $botId);
            return;
        }

        $book = $this->libraryRepository->findBook($bookId, $botId);
        if (!$book) {
            BotHelper::sendMessage($bot, trans('book_library.book_not_found'));
            return;
        }

        $this->deliveryService->deliverBook($bot, $botUser, $book, $botId, $type, false, true);
    }

    private function deliverRandomBook(Telegram $bot, BotUsers $botUser, int $botId, string $type, ?int $genreId): void
    {
        if (!$this->planService->canDeliver($botUser, $botId)) {
            BotHelper::sendMessage($bot, trans('book_library.quota_exceeded'));
            $this->showPlanMenu($bot, $botUser, $botId);
            return;
        }

        $book = $this->libraryRepository->getRandomBook($botId, $genreId);
        if (!$book) {
            BotHelper::sendMessage($bot, trans('book_library.no_books'));
            return;
        }

        $this->deliveryService->deliverBook($bot, $botUser, $book, $botId, $type, true, false);
    }

    private function showMyBooks(Telegram $bot, BotUsers $botUser, int $botId): void
    {
        $userBooks = $this->libraryRepository->getUserBooks($botUser->id, $botId, 10);
        if ($userBooks->isEmpty()) {
            BotHelper::sendMessage($bot, trans('book_library.my_books_empty'));
            return;
        }

        $message = trans('book_library.my_books_title') . "\n\n";
        foreach ($userBooks as $index => $userBook) {
            $title = $userBook->book->title ?? trans('book_library.unknown_book');
            $message .= ($index + 1) . '. ' . $title . "\n";
        }

        BotHelper::sendMessage($bot, $message);
    }

    private function showPlanMenu(Telegram $bot, BotUsers $botUser, int $botId): void
    {
        $subscription = $this->bookLibraryService->getOrCreateSubscription($botUser, $botId);
        $currentPlan = trans(config('book_library.plans.' . $subscription->plan . '.label_key', 'book_library.plan.free'));

        $message = trans('book_library.plan_current', ['plan' => $currentPlan]) . "\n";
        $message .= $this->bookLibraryService->buildProgressBar($subscription) . "\n\n";
        $message .= trans('book_library.plan_choose');

        $keyboard = [];
        foreach (config('book_library.paid_plans', []) as $planKey) {
            $plan = config('book_library.plans.' . $planKey);
            if (!$plan) {
                continue;
            }
            $label = trans($plan['label_key']) . ' - ' . number_format($plan['price']) . ' ' . trans('book_library.currency');
            $keyboard[] = [$bot->buildInlineKeyBoardButton($label, callback_data: "bl:plan:{$planKey}")];
        }

        $inlineKeyboard = $bot->buildInlineKeyBoard($keyboard);
        BotHelper::sendKeyboardMessage($bot, $message, $inlineKeyboard);
    }
}
