<?php

return [
    'welcome' => "Hello 👋\n\nWelcome to the Smart Book Library.\n\nGet daily audio summaries, PDFs, and infographics.\n\n👇 Where shall we start?",
    'menu_prompt' => 'Choose an option:',
    'menu_intro' => '📚 Discover Books',
    'menu_random' => '🎲 Random Pick',
    'menu_my_books' => '⭐ My Books',
    'menu_upgrade' => '💎 Upgrade Plan',
    'use_menu' => 'Please use the menu below.',
    'genre_prompt' => 'Which topic interests you most?',
    'genre_next' => 'More ➡️',
    'genre_not_found' => 'Genre not found.',
    'no_genres' => 'No genres configured yet.',
    'books_prompt' => 'Four book suggestions in «:genre»',
    'books_next' => '➡️ More books',
    'random_pick' => '🎲 Random pick',
    'no_books' => 'No books found in this section.',
    'book_not_found' => 'Book not found.',
    'preparing' => '🎧 Preparing your file...',
    'no_audio' => 'Audio is not available for this book.',
    'delivery_error' => 'Delivery failed. Please try again.',
    'reader_required' => 'Please /start the reader bot first so content can be delivered there.',
    'reader_welcome' => "📖 Welcome to the Book Reader bot!\n\nAudio summaries, PDFs and infographics are delivered here.\n\nFor full guide: /help",
    'reader_help' => "📖 Book Reader — Help\n\n" .
        "This bot delivers book content. Browse and pick books in the main Smart Book Library bot.\n\n" .
        "Commands:\n" .
        "/start — Activate delivery to this chat\n" .
        "/help — Show this guide\n\n" .
        "After each book:\n" .
        "📄 Get PDF\n" .
        "🖼 Get infographic\n" .
        "🎧 Replay audio\n\n" .
        "Tip: /start both this bot and the main library bot.",
    'reader_main_bot_hint' => "📚 Main library bot: @:username\nChoose books there; content arrives here.",
    'reader_use_help' => "Send /help for guidance.\nUse the main library bot to pick books.",
    'main_help' => "📚 Smart Book Library — Help\n\n" .
        "Commands:\n" .
        "/start — Main menu\n" .
        "/help — This guide\n\n" .
        "Menu:\n" .
        "📚 Discover Books — genres and summaries\n" .
        "🎲 Random Pick — random book\n" .
        "⭐ My Books — history\n" .
        "💎 Upgrade Plan — more quota\n\n" .
        "If you have a separate reader bot, /start it too for audio and PDF delivery.",
    'sent_to_reader' => '✅ Content was sent to your reader bot.',
    'progress' => "Progress:\n:bar\n:used of :limit books (:percent%)",
    'quota_exceeded' => 'Your book quota is used up. Upgrade your plan to continue.',
    'quick_actions' => 'More options:',
    'qa_pdf' => '📄 Get PDF',
    'qa_infographic' => '🖼 Get infographic',
    'qa_replay' => '🎧 Replay audio',
    'media_not_available' => 'This content type is not available for this book.',
    'my_books_title' => '📚 Your received books:',
    'my_books_empty' => 'You have not received any books yet.',
    'unknown_book' => 'Unknown book',
    'plan_current' => '💎 Current plan: :plan',
    'plan_choose' => 'Select a plan to request purchase:',
    'plan_invalid' => 'Invalid plan selected.',
    'plan_pending' => 'Your previous request is still pending.',
    'plan_requested' => '✅ Request submitted. An admin will activate your plan after payment.',
    'plan_not_found' => 'Request not found or already processed.',
    'plan_confirmed' => 'Plan activated successfully.',
    'plan_activated' => '✅ Your plan is active! You can receive more books now.',
    'currency' => 'IRR',
    'plan' => [
        'free' => 'Free (3 books)',
        'plan_100' => '🥉 100 books',
        'plan_300' => '🥈 300 books',
        'plan_1000' => '🥇 1000 books',
    ],
];
