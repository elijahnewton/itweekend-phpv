<?php
require_once __DIR__ . '/config.php';

function get_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dir = dirname(DB_PATH);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON;');
        init_schema($pdo);
    }
    return $pdo;
}

function init_schema(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL UNIQUE,
            name TEXT NOT NULL DEFAULT '',
            password TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'student',
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            updated_at TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS languages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            slug TEXT NOT NULL UNIQUE,
            description TEXT,
            icon TEXT,
            color TEXT,
            level TEXT,
            estimated_hours INTEGER,
            order_index INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS topics (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            language_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            slug TEXT NOT NULL,
            description TEXT,
            order_index INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            UNIQUE(language_id, slug),
            FOREIGN KEY (language_id) REFERENCES languages(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS lessons (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            topic_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            slug TEXT NOT NULL,
            content_html TEXT,
            code_example TEXT,
            code_language TEXT NOT NULL DEFAULT 'plaintext',
            order_index INTEGER NOT NULL DEFAULT 0,
            estimated_minutes INTEGER,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            UNIQUE(topic_id, slug),
            FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS quizzes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            lesson_id INTEGER,
            language_id INTEGER,
            title TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
            FOREIGN KEY (language_id) REFERENCES languages(id) ON DELETE SET NULL
        );

        CREATE TABLE IF NOT EXISTS quiz_questions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            quiz_id INTEGER NOT NULL,
            prompt TEXT NOT NULL,
            explanation TEXT,
            order_index INTEGER NOT NULL DEFAULT 0,
            FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS quiz_choices (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            question_id INTEGER NOT NULL,
            choice_text TEXT NOT NULL,
            is_correct INTEGER NOT NULL DEFAULT 0,
            FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS videos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            url TEXT NOT NULL,
            duration_seconds INTEGER,
            description TEXT,
            language_id INTEGER,
            topic_id INTEGER,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY (language_id) REFERENCES languages(id) ON DELETE SET NULL,
            FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE SET NULL
        );

        CREATE TABLE IF NOT EXISTS user_progress (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            lesson_id INTEGER,
            video_id INTEGER,
            last_accessed_at TEXT NOT NULL DEFAULT (datetime('now')),
            completed_at TEXT,
            completion_percent INTEGER NOT NULL DEFAULT 0,
            quiz_best_score INTEGER NOT NULL DEFAULT 0,
            quiz_last_score INTEGER NOT NULL DEFAULT 0,
            quiz_attempts INTEGER NOT NULL DEFAULT 0,
            UNIQUE(user_id, lesson_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
            FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE
        );

        CREATE INDEX IF NOT EXISTS idx_topics_lang ON topics(language_id);
        CREATE INDEX IF NOT EXISTS idx_lessons_topic ON lessons(topic_id);
        CREATE INDEX IF NOT EXISTS idx_progress_user ON user_progress(user_id);
    ");

    // Seed default admin and content if DB is fresh
    $count = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($count === 0) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)")
            ->execute(['Administrator', 'admin@lms.local', $hash, ROLE_ADMIN]);
        seed_content($pdo);
    }
}

function seed_content(PDO $pdo): void {
    // Seed the three core tracks from content.py
    $languages = [
        [
            'name' => 'C Programming',
            'slug' => 'c-programming',
            'description' => 'Build a rock-solid foundation in systems programming. Master pointers, memory, and data structures through hands-on exercises.',
            'icon' => '⚙️',
            'color' => 'from-indigo-500 to-violet-600',
            'level' => 'Beginner',
            'estimated_hours' => 20,
            'order_index' => 1,
        ],
        [
            'name' => 'Web Development',
            'slug' => 'web-development',
            'description' => 'Go from zero to a live web page. Learn HTML structure, CSS styling, and JavaScript interactivity — the three pillars of the modern web.',
            'icon' => '🌐',
            'color' => 'from-blue-500 to-cyan-500',
            'level' => 'Beginner',
            'estimated_hours' => 30,
            'order_index' => 2,
        ],
        [
            'name' => 'Cyber Security',
            'slug' => 'cyber-security',
            'description' => 'Learn how attackers think, how networks work, and how to defend systems. Covers ethical hacking, cryptography, and real-world tools.',
            'icon' => '🔒',
            'color' => 'from-violet-500 to-purple-600',
            'level' => 'Intermediate',
            'estimated_hours' => 25,
            'order_index' => 3,
        ],
    ];

    $lang_stmt = $pdo->prepare(
        'INSERT INTO languages (name, slug, description, icon, color, level, estimated_hours, order_index)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $topic_stmt = $pdo->prepare(
        'INSERT INTO topics (language_id, name, slug, description, order_index) VALUES (?, ?, ?, ?, ?)'
    );
    $lesson_stmt = $pdo->prepare(
        'INSERT INTO lessons (topic_id, title, slug, content_html, code_example, code_language, order_index, estimated_minutes)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $seed_data = [
        'c-programming' => [
            [
                'name' => 'Fundamentals',
                'slug' => 'fundamentals',
                'description' => 'Core C syntax, types, and control flow',
                'lessons' => [
                    ['title' => 'Introduction to C', 'slug' => 'c-intro', 'minutes' => 20,
                     'content' => 'C is a general-purpose programming language that has influenced nearly every modern language. In this lesson you will set up your development environment, write your first program, and understand the compilation pipeline.',
                     'code' => "#include <stdio.h>\n\nint main(void) {\n    printf(\"Hello, Weekend IT!\\n\");\n    return 0;\n}", 'lang' => 'c'],
                    ['title' => 'Variables, Types & Operators', 'slug' => 'c-variables', 'minutes' => 25,
                     'content' => 'C is statically typed. Every variable must be declared with an explicit type before use. We cover int, float, double, char, and the most common arithmetic and bitwise operators.',
                     'code' => "int age = 22;\nfloat gpa = 3.7f;\nchar grade = 'A';\nprintf(\"%d %f %c\\n\", age, gpa, grade);", 'lang' => 'c'],
                    ['title' => 'Control Flow', 'slug' => 'c-control-flow', 'minutes' => 25,
                     'content' => 'Master if/else, switch, while, for, and do-while. Learn how to structure decision-making and iteration in C programs.',
                     'code' => "for (int i = 1; i <= 5; i++) {\n    printf(i % 2 == 0 ? \"even\\n\" : \"odd\\n\");\n}", 'lang' => 'c'],
                    ['title' => 'Functions & Scope', 'slug' => 'c-functions', 'minutes' => 30,
                     'content' => 'Functions are the building blocks of C programs. This lesson covers function declarations, definitions, parameter passing (by value vs. by reference), and variable scope/lifetime.',
                     'code' => "int add(int a, int b) {\n    return a + b;\n}\n\nint main(void) {\n    printf(\"%d\\n\", add(3, 4)); // 7\n    return 0;\n}", 'lang' => 'c'],
                    ['title' => 'Pointers & Memory Management', 'slug' => 'c-pointers', 'minutes' => 40,
                     'content' => 'Pointers are what make C powerful — and dangerous if misused. Learn address-of (&), dereference (*), pointer arithmetic, malloc/free, and common pitfalls like null dereferences and memory leaks.',
                     'code' => "int x = 10;\nint *p = &x;\nprintf(\"value: %d, address: %p\\n\", *p, (void *)p);\n\nint *arr = malloc(5 * sizeof(int));\nif (!arr) { perror(\"malloc\"); return 1; }\n// ... use arr ...\nfree(arr);", 'lang' => 'c'],
                ],
            ],
            [
                'name' => 'Arrays & Strings',
                'slug' => 'arrays-strings',
                'description' => 'Working with arrays, strings, and string functions',
                'lessons' => [
                    ['title' => 'Arrays', 'slug' => 'c-arrays', 'minutes' => 25,
                     'content' => 'Arrays store multiple values of the same type in contiguous memory. Learn how to declare, initialize, and iterate over arrays in C.',
                     'code' => "int scores[5] = {90, 85, 78, 92, 88};\nfor (int i = 0; i < 5; i++) {\n    printf(\"Score %d: %d\\n\", i+1, scores[i]);\n}", 'lang' => 'c'],
                    ['title' => 'Strings in C', 'slug' => 'c-strings', 'minutes' => 30,
                     'content' => 'In C, strings are null-terminated arrays of char. Learn to use the <string.h> library functions: strlen, strcpy, strcat, strcmp.',
                     'code' => "#include <string.h>\nchar name[50] = \"Weekend\";\nstrcat(name, \" IT\");\nprintf(\"%s (%zu chars)\\n\", name, strlen(name));", 'lang' => 'c'],
                ],
            ],
        ],
        'web-development' => [
            [
                'name' => 'HTML & CSS',
                'slug' => 'html-css',
                'description' => 'Building structure and style for the web',
                'lessons' => [
                    ['title' => 'HTML Foundations', 'slug' => 'web-html', 'minutes' => 20,
                     'content' => 'HTML (HyperText Markup Language) defines the structure of a web page. Learn about elements, attributes, semantic tags, forms, and accessibility best-practices.',
                     'code' => "<!DOCTYPE html>\n<html lang=\"en\">\n  <head>\n    <meta charset=\"UTF-8\" />\n    <title>My Page</title>\n  </head>\n  <body>\n    <h1>Hello World</h1>\n    <p>Welcome to web dev!</p>\n  </body>\n</html>", 'lang' => 'html'],
                    ['title' => 'CSS Styling & Layout', 'slug' => 'web-css', 'minutes' => 30,
                     'content' => 'CSS (Cascading Style Sheets) controls the visual presentation of HTML. This lesson covers the box model, Flexbox, Grid, and responsive media queries.',
                     'code' => ".card {\n  display: flex;\n  flex-direction: column;\n  padding: 1.5rem;\n  border-radius: 0.75rem;\n  box-shadow: 0 2px 8px rgba(0,0,0,.1);\n}", 'lang' => 'css'],
                ],
            ],
            [
                'name' => 'JavaScript',
                'slug' => 'javascript',
                'description' => 'Making web pages interactive',
                'lessons' => [
                    ['title' => 'JavaScript Basics', 'slug' => 'web-js', 'minutes' => 35,
                     'content' => 'JavaScript brings interactivity to the browser. Learn variables (let/const), functions, events, and DOM manipulation.',
                     'code' => "document.querySelector('#btn').addEventListener('click', () => {\n  document.querySelector('#msg').textContent = 'Hello!';\n});", 'lang' => 'javascript'],
                    ['title' => 'Fetch & APIs', 'slug' => 'web-fetch', 'minutes' => 30,
                     'content' => 'Use the Fetch API to request data from servers. Learn about promises, async/await, and working with JSON responses.',
                     'code' => "const res = await fetch('/api/data');\nconst json = await res.json();\nconsole.log(json);", 'lang' => 'javascript'],
                ],
            ],
        ],
        'cyber-security' => [
            [
                'name' => 'Foundations',
                'slug' => 'foundations',
                'description' => 'Core security concepts and threat landscape',
                'lessons' => [
                    ['title' => 'Security Fundamentals', 'slug' => 'sec-intro', 'minutes' => 20,
                     'content' => 'Understand the CIA triad (Confidentiality, Integrity, Availability), common threat actors, and the attack lifecycle. This lesson sets the foundation for all security work.',
                     'code' => "# Basic nmap scan example\nnmap -sV -p 1-1000 target_host\n# -sV: version detection\n# -p: port range", 'lang' => 'bash'],
                    ['title' => 'Networking for Security', 'slug' => 'sec-networking', 'minutes' => 30,
                     'content' => 'Security professionals must understand TCP/IP, DNS, HTTP/S, and common network protocols. Learn how packets travel and where attackers intercept them.',
                     'code' => "# Capture packets with tcpdump\ntcpdump -i eth0 -n 'port 80'\n# Wireshark filter for HTTP\nhttp.request.method == \"GET\"", 'lang' => 'bash'],
                ],
            ],
            [
                'name' => 'Ethical Hacking',
                'slug' => 'ethical-hacking',
                'description' => 'Penetration testing techniques and tools',
                'lessons' => [
                    ['title' => 'Reconnaissance', 'slug' => 'sec-recon', 'minutes' => 25,
                     'content' => 'Reconnaissance is the first phase of ethical hacking. Learn passive (OSINT) and active techniques for gathering information about targets legally and ethically.',
                     'code' => "# WHOIS lookup\nwhois example.com\n# DNS enumeration\nnslookup -type=MX example.com", 'lang' => 'bash'],
                ],
            ],
        ],
    ];

    $lang_ids = [];
    foreach ($languages as $lang) {
        $lang_stmt->execute([
            $lang['name'], $lang['slug'], $lang['description'],
            $lang['icon'], $lang['color'], $lang['level'],
            $lang['estimated_hours'], $lang['order_index'],
        ]);
        $lang_ids[$lang['slug']] = (int)$pdo->lastInsertId();
    }

    foreach ($seed_data as $lang_slug => $topics) {
        $lang_id = $lang_ids[$lang_slug] ?? null;
        if (!$lang_id) continue;
        foreach ($topics as $ti => $topic) {
            $topic_stmt->execute([$lang_id, $topic['name'], $topic['slug'], $topic['description'], $ti]);
            $topic_id = (int)$pdo->lastInsertId();
            foreach ($topic['lessons'] as $li => $lesson) {
                $lesson_stmt->execute([
                    $topic_id, $lesson['title'], $lesson['slug'],
                    $lesson['content'], $lesson['code'], $lesson['lang'],
                    $li, $lesson['minutes'],
                ]);
            }
        }
    }

    // Seed a sample quiz for the first C lesson
    $first_lesson = $pdo->query(
        "SELECT l.id FROM lessons l
         JOIN topics t ON l.topic_id = t.id
         JOIN languages lg ON t.language_id = lg.id
         WHERE lg.slug = 'c-programming' AND l.slug = 'c-intro'
         LIMIT 1"
    )->fetchColumn();

    if ($first_lesson) {
        $pdo->prepare('INSERT INTO quizzes (lesson_id, title) VALUES (?, ?)')->execute([$first_lesson, 'C Intro Quiz']);
        $qid = (int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO quiz_questions (quiz_id, prompt, explanation, order_index) VALUES (?, ?, ?, ?)')
            ->execute([$qid, 'What does the #include directive do in C?', 'The #include directive tells the preprocessor to include the contents of a header file.', 0]);
        $qqid = (int)$pdo->lastInsertId();
        foreach ([
            ['Declares a function', false],
            ['Includes a header file', true],
            ['Defines a variable', false],
            ['Starts the main function', false],
        ] as [$text, $correct]) {
            $pdo->prepare('INSERT INTO quiz_choices (question_id, choice_text, is_correct) VALUES (?, ?, ?)')
                ->execute([$qqid, $text, $correct ? 1 : 0]);
        }
    }
}
