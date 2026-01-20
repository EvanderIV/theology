<?php
// --- SERVER SIDE LOGIC ---
// Scan the 'articles' directory and build a lightweight index for the frontend.
$libraryIndex = [];
$articlesDir = 'articles';

if (is_dir($articlesDir)) {
    $files = glob($articlesDir . '/*.xml');
    foreach ($files as $file) {
        // Suppress errors in case of malformed XML
        $xml = @simplexml_load_file($file);
        if ($xml) {
            // Extract basic metadata
            $slug = basename($file, '.xml');
            $title = (string)$xml->title;
            $author = (string)$xml->author;
            
            // Get a snippet from the first paragraph
            $firstPara = (string)$xml->body->paragraph[0];
            $snippet = substr(strip_tags($firstPara), 0, 200) . '...';
            
            // Extract tags if they exist
            $tags = [];
            if (isset($xml->tags) && isset($xml->tags->tag)) {
                foreach ($xml->tags->tag as $t) {
                    $tags[] = trim((string)$t);
                }
            }
            
            // Extract text content for search indexing (simple concatenation)
            $rawText = strtolower($title . ' ' . $author . ' ' . implode(' ', $tags) . ' ' . strip_tags($xml->body->asXML()));
            // FUZZY SEARCH UPDATE: Replace dashes with spaces so "Works-Based" matches "Works Based"
            $searchText = str_replace('-', ' ', $rawText);

            $libraryIndex[] = [
                'slug' => $slug,
                'title' => $title,
                'author' => $author,
                'snippet' => $snippet,
                'tags' => $tags,
                'search_text' => $searchText
            ];
        }
    }
}

// --- SERVER SIDE LOGIC ---
// Scan the 'versions' directory to get available Bible versions.
$versionsDir = 'versions';
$bibleVersions = [];

if (is_dir($versionsDir)) {
    $files = glob($versionsDir . '/*.json');
    foreach ($files as $file) {
        $content = json_decode(file_get_contents($file), true);
        if (isset($content['__VERSION__'])) {
            $filename = basename($file, '.json');
            $bibleVersions[$filename] = $content['__VERSION__'] . " ($filename)";
        }
    }
}

// --- SERVER SIDE LOGIC ---
// Extend libraryIndex to include a mapping of slugs to titles for related readings.
$articleTitles = [];

foreach ($libraryIndex as $article) {
    $articleTitles[$article['slug']] = $article['title'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Theological Discourse</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Google Fonts: Merriweather for body, Inter for UI -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Merriweather:ital,wght@0,300;0,400;0,700;1,300;1,400&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body class="min-h-screen flex flex-col">

    <!-- Anti-Flash Script -->
    <script>
        (function() {
            try {
                if(localStorage.getItem('darkMode') === 'true') document.body.classList.add('dark-mode');
                if(localStorage.getItem('monoMode') === 'true') document.body.classList.add('mono-mode');
                if(localStorage.getItem('contrastMode') === 'true') document.body.classList.add('high-contrast');
            } catch(e) {}
        })();
    </script>

    <!-- Header -->
    <header class="w-full py-8 border-b border-stone-200">
        <div class="max-w-6xl mx-auto px-6 flex justify-between items-center relative">
            <a href="index.php" class="uppercase tracking-widest text-xs font-semibold ui-font text-stone-500 hover:text-stone-800 transition">Theological Journal</a>
            
            <!-- Settings Area -->
            <div class="relative">
                <button id="settings-btn" class="flex items-center space-x-2 text-stone-500 hover:text-stone-800 transition focus:outline-none">
                    <span class="text-sm ui-font uppercase tracking-wider font-medium">Settings</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd" />
                    </svg>
                </button>

                <!-- Dropdown -->
                <div id="settings-dropdown" class="hidden absolute right-0 mt-3 w-72 bg-white border border-stone-200 shadow-xl rounded-sm p-5 z-50 transform origin-top-right transition-all duration-200">
                    <h3 class="ui-font text-xs font-bold uppercase tracking-widest text-stone-400 mb-4 border-b border-stone-100 pb-2">Preferences</h3>
                    
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex flex-col">
                            <span class="text-sm font-medium text-stone-700 ui-font">Dark Mode</span>
                            <span class="text-xs text-stone-400 ui-font">Easier on the eyes</span>
                        </div>
                        <div class="relative inline-block w-10 align-middle select-none transition duration-200 ease-in">
                            <input type="checkbox" name="toggle-dark" id="toggle-dark" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer hidden"/>
                            <label for="toggle-dark" class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 cursor-pointer"></label>
                        </div>
                    </div>

                    <div class="flex items-center justify-between mb-4">
                        <div class="flex flex-col">
                            <span class="text-sm font-medium text-stone-700 ui-font">Monospace</span>
                            <span class="text-xs text-stone-400 ui-font">Typewriter style</span>
                        </div>
                        <div class="relative inline-block w-10 align-middle select-none transition duration-200 ease-in">
                            <input type="checkbox" name="toggle-mono" id="toggle-mono" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer hidden"/>
                            <label for="toggle-mono" class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 cursor-pointer"></label>
                        </div>
                    </div>

                    <div class="flex items-center justify-between mb-4">
                        <div class="flex flex-col">
                            <span class="text-sm font-medium text-stone-700 ui-font">High Contrast</span>
                            <span class="text-xs text-stone-400 ui-font">Sharpen definition</span>
                        </div>
                        <div class="relative inline-block w-10 align-middle select-none transition duration-200 ease-in">
                            <input type="checkbox" name="toggle-contrast" id="toggle-contrast" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer hidden"/>
                            <label for="toggle-contrast" class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 cursor-pointer"></label>
                        </div>
                    </div>

                    <!-- Dropdown for Bible Version Selector -->
                    <div class="mb-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-stone-700 ui-font">Bible Translation</span>
                        </div>
                        <div class="mt-2 flex justify-center">
                            <select id="bible-version-selector" class="p-2 border border-stone-300 rounded-sm text-stone-700 bg-white">
                                <?php foreach ($bibleVersions as $key => $label): ?>
                                    <option value="<?= $key ?>" <?= $key === 'ESV' ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content Area -->
    <main id="main-container" class="flex-grow w-full max-w-6xl mx-auto px-6 py-12">
        <!-- LANDING STATE -->
        <div id="landing-view" class="hidden">
            <div class="max-w-3xl mx-auto mb-16 mt-8">
                <div class="text-center">
                    <h1 class="text-4xl lg:text-5xl font-bold text-stone-900 mb-6 leading-tight">Welcome</h1>
                    <div class="h-1 w-20 bg-red-800 mx-auto mb-8"></div>
                    <p class="text-xl text-stone-600 mb-10 font-serif leading-relaxed">
                        Please select an article below to begin reading, or search the archives for specific theological topics.
                    </p>
                </div>

                <div class="relative z-10">
                    <input type="text" id="search-input" placeholder="Search by title, author, or content..." class="search-input w-full p-4 pl-6 text-lg bg-white border border-stone-300 rounded-sm text-stone-700 placeholder-stone-400 transition-all duration-300">
                    <div class="absolute right-4 top-4 text-stone-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                </div>

                <div id="filter-section" class="mt-4 hidden border-t border-stone-200 pt-6">
                    <div class="flex flex-col md:flex-row md:items-start gap-8">
                        <div class="w-full md:w-1/4">
                            <h4 class="text-xs font-bold uppercase tracking-widest text-stone-400 mb-3 ui-font">Logic</h4>
                            <div class="flex flex-col space-y-2">
                                <div class="flex items-center">
                                    <input type="radio" id="op-or" name="filter-op" value="OR" class="op-radio hidden" checked>
                                    <label for="op-or" class="text-sm px-3 py-1 border border-stone-200 rounded-sm cursor-pointer hover:border-stone-400 w-full text-center transition-colors">OR (Any)</label>
                                </div>
                                <div class="flex items-center">
                                    <input type="radio" id="op-and" name="filter-op" value="AND" class="op-radio hidden">
                                    <label for="op-and" class="text-sm px-3 py-1 border border-stone-200 rounded-sm cursor-pointer hover:border-stone-400 w-full text-center transition-colors">AND (All)</label>
                                </div>
                                <div class="flex items-center">
                                    <input type="radio" id="op-xor" name="filter-op" value="XOR" class="op-radio hidden">
                                    <label for="op-xor" class="text-sm px-3 py-1 border border-stone-200 rounded-sm cursor-pointer hover:border-stone-400 w-full text-center transition-colors">XOR (One)</label>
                                </div>
                                <div class="flex items-center">
                                    <input type="radio" id="op-nor" name="filter-op" value="NOR" class="op-radio hidden">
                                    <label for="op-nor" class="text-sm px-3 py-1 border border-stone-200 rounded-sm cursor-pointer hover:border-stone-400 w-full text-center transition-colors">NOR (None)</label>
                                </div>
                            </div>
                        </div>
                        <div class="w-full md:w-3/4">
                            <div class="flex justify-between items-center mb-3">
                                <h4 class="text-xs font-bold uppercase tracking-widest text-stone-400 ui-font">Filter by Tags</h4>
                                <button id="clear-tags" class="text-xs text-stone-400 hover:text-red-800 transition ui-font underline hidden">Clear All</button>
                            </div>
                            <div id="tag-container" class="flex flex-wrap gap-2"></div>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <button id="toggle-filters" class="text-xs font-bold uppercase tracking-widest text-stone-400 hover:text-stone-600 transition ui-font flex items-center justify-center mx-auto">
                        <span class="mr-1">Advanced Filters</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 transform transition-transform duration-200" id="filter-chevron" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                </div>
                <p id="result-count" class="mt-4 text-center text-xs text-stone-400 ui-font uppercase tracking-wide hidden">Showing all articles</p>
            </div>
            <div id="articles-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8"></div>
            <div id="no-results" class="hidden text-center py-12">
                <p class="text-stone-500 italic font-serif text-lg">No articles found matching your query.</p>
            </div>
        </div>

        <!-- ARTICLE STATE (Reader) -->
        <div id="article-view" class="hidden grid grid-cols-1 lg:grid-cols-12 gap-12">
            <article class="lg:col-span-8 relative">
                <div class="mb-6">
                    <a href="index.php" class="ui-font text-xs font-bold text-stone-400 hover:text-red-800 transition uppercase tracking-wide flex items-center mb-4">
                        &larr; Back to Library
                    </a>
                    <div id="article-header">
                        <div class="h-8 w-3/4 bg-stone-200 animate-pulse rounded"></div>
                    </div>
                </div>
                <div id="article-body" class="prose prose-stone prose-lg max-w-none text-justify leading-loose"></div>
            </article>

            <aside class="lg:col-span-4 space-y-12 border-l border-stone-100 lg:pl-12">
                <div id="sidebar-related" class="hidden">
                    <h3 class="ui-font text-xs font-bold uppercase tracking-widest text-stone-400 mb-4">Related Readings</h3>
                    <ul id="related-list" class="space-y-3"></ul>
                </div>
                <div id="sidebar-references" class="hidden">
                    <h3 class="ui-font text-xs font-bold uppercase tracking-widest text-stone-400 mb-4">Sources & References</h3>
                    <ul id="reference-list" class="text-sm space-y-4 text-stone-600"></ul>
                </div>
                <div id="sola-scriptura" class="bg-stone-50 p-6 rounded-sm border border-stone-100">
                    <h3 class="ui-font font-serif italic text-lg text-stone-800 mb-2">Sola Scriptura</h3>
                    <p class="text-xs leading-relaxed text-stone-500 ui-font">
                        This platform is designed to facilitate deep theological reading without distraction. Hover over scripture references to view the text in context.
                    </p>
                </div>
            </aside>
        </div>
    </main>

    <!-- Footer -->
    <footer class="border-t border-stone-200 py-8 mt-12">
        <div class="max-w-6xl mx-auto px-6 text-center text-stone-400 text-xs ui-font">
            &copy; <?php echo date("Y"); ?> eminich.com
        </div>
    </footer>

    <!-- Tooltip Element -->
    <div id="bible-tooltip" class="tooltip-container fixed w-80 bg-white shadow-xl border border-stone-200 rounded-sm p-5 text-sm leading-relaxed text-stone-700 hidden">
        <div id="tooltip-loading" class="text-center py-2 hidden">
            <span class="spinner"></span>
        </div>
        <div id="tooltip-content">
            <div class="context-group-top mb-2">
                <div class="context-line w-full"></div>
                <div class="context-line w-5/6"></div>
                <div class="context-line w-2/3"></div>
            </div>
            <div id="tooltip-verse-text" class="font-serif text-stone-800 my-2"></div>
            <div class="context-group-bottom mt-2">
                <div class="context-line w-full"></div>
                <div class="context-line w-3/4"></div>
            </div>
            <div id="tooltip-ref" class="text-right mt-3 text-xs font-bold text-stone-400 ui-font uppercase tracking-wide"></div>
        </div>
    </div>

    <!-- Application Logic -->
    <script>
        // --- 1. DATA INJECTION FROM PHP ---
        const LIBRARY_INDEX = <?php echo json_encode($libraryIndex); ?> || [];
        const BIBLE_VERSIONS = <?php echo json_encode($bibleVersions); ?> || {};
        const ARTICLE_TITLES = <?php echo json_encode($articleTitles); ?>;

        // --- Configuration ---
        const API_ENDPOINT = "get_verse.php?reference="; // Updated to use local backend API

        // --- 2. SETTINGS & PREFERENCES MANAGER ---
        const Settings = {
            init() {
                this.darkToggle = document.getElementById('toggle-dark');
                this.monoToggle = document.getElementById('toggle-mono');
                this.contrastToggle = document.getElementById('toggle-contrast');
                this.dropdown = document.getElementById('settings-dropdown');
                this.btn = document.getElementById('settings-btn');
                this.versionSelector = document.getElementById('bible-version-selector');

                this.loadState('darkMode', this.darkToggle, this.toggleDark);
                this.loadState('monoMode', this.monoToggle, this.toggleMono);
                this.loadState('contrastMode', this.contrastToggle, this.toggleContrast);

                this.btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.dropdown.classList.toggle('hidden');
                });

                document.addEventListener('click', (e) => {
                    if (!this.dropdown.contains(e.target) && !this.btn.contains(e.target)) {
                        this.dropdown.classList.add('hidden');
                    }
                });

                this.darkToggle.addEventListener('change', () => this.saveState('darkMode', this.darkToggle.checked, this.toggleDark));
                this.monoToggle.addEventListener('change', () => this.saveState('monoMode', this.monoToggle.checked, this.toggleMono));
                this.contrastToggle.addEventListener('change', () => this.saveState('contrastMode', this.contrastToggle.checked, this.toggleContrast));

                // Bible version change event
                this.versionSelector.addEventListener('change', () => {
                    const selectedVersion = this.versionSelector.value;
                    localStorage.setItem('bibleVersion', selectedVersion);
                    
                    // Clear the cache for verses
                    if (window.cache) {
                        Object.keys(window.cache).forEach(key => delete window.cache[key]);
                    }

                    // Optionally, you can trigger a reload or re-initialization if needed
                    console.log(`Bible version changed to: ${selectedVersion}. Cache cleared. Refreshing...`);
                    location.reload();
                });

                // Load the saved Bible version
                const savedVersion = localStorage.getItem('bibleVersion') || 'ESV';
                this.versionSelector.value = savedVersion;
            },

            loadState(key, checkbox, action) {
                const isEnabled = localStorage.getItem(key) === 'true';
                checkbox.checked = isEnabled;
                action(isEnabled);
            },

            saveState(key, isEnabled, action) {
                localStorage.setItem(key, isEnabled);
                action(isEnabled);
            },

            toggleDark(enable) {
                enable ? document.body.classList.add('dark-mode') : document.body.classList.remove('dark-mode');
            },

            toggleMono(enable) {
                enable ? document.body.classList.add('mono-mode') : document.body.classList.remove('mono-mode');
            },

            toggleContrast(enable) {
                enable ? document.body.classList.add('high-contrast') : document.body.classList.remove('high-contrast');
            }
        };

        // --- 3. Router & Initialization ---
        document.addEventListener('DOMContentLoaded', () => {
            Settings.init();

            const urlParams = new URLSearchParams(window.location.search);
            const articleSlug = urlParams.get('article');

            if (articleSlug) {
                document.getElementById('article-view').classList.remove('hidden');
                loadArticle(articleSlug);
            } else {
                document.getElementById('landing-view').classList.remove('hidden');
                initLandingPage();
            }
        });

        // --- 4. Landing Page Logic ---
        function initLandingPage() {
            const searchInput = document.getElementById('search-input');
            const grid = document.getElementById('articles-grid');
            const noResults = document.getElementById('no-results');
            const countLabel = document.getElementById('result-count');
            
            const filterToggle = document.getElementById('toggle-filters');
            const filterSection = document.getElementById('filter-section');
            const filterChevron = document.getElementById('filter-chevron');
            const tagContainer = document.getElementById('tag-container');
            const clearTagsBtn = document.getElementById('clear-tags');
            const operatorRadios = document.querySelectorAll('input[name="filter-op"]');

            const allTags = new Set();
            LIBRARY_INDEX.forEach(item => {
                if (item.tags && Array.isArray(item.tags)) {
                    item.tags.forEach(t => allTags.add(t));
                }
            });

            if (allTags.size === 0) {
                tagContainer.innerHTML = '<span class="text-xs text-stone-400 italic font-serif">No tags available in current index.</span>';
            } else {
                Array.from(allTags).sort().forEach(tag => {
                    const wrapper = document.createElement('div');
                    wrapper.className = "inline-block";
                    wrapper.innerHTML = `
                        <input type="checkbox" id="tag-${tag}" value="${tag}" class="tag-checkbox hidden">
                        <label for="tag-${tag}" class="tag-badge text-xs px-3 py-1 border border-stone-200 rounded-full cursor-pointer hover:border-stone-400 text-stone-500 font-medium">${tag}</label>
                    `;
                    tagContainer.appendChild(wrapper);
                });
            }

            filterToggle.addEventListener('click', () => {
                filterSection.classList.toggle('hidden');
                filterChevron.classList.toggle('rotate-180');
            });

            clearTagsBtn.addEventListener('click', () => {
                document.querySelectorAll('.tag-checkbox').forEach(cb => cb.checked = false);
                runFilter();
            });

            searchInput.addEventListener('input', runFilter);
            document.querySelectorAll('.tag-checkbox').forEach(cb => cb.addEventListener('change', runFilter));
            operatorRadios.forEach(radio => radio.addEventListener('change', runFilter));

            function runFilter() {
                const query = searchInput.value.toLowerCase().replace(/-/g, ' ');
                const selectedTags = Array.from(document.querySelectorAll('.tag-checkbox:checked')).map(cb => cb.value);
                const operator = document.querySelector('input[name="filter-op"]:checked').value;

                if (selectedTags.length > 0) {
                    clearTagsBtn.classList.remove('hidden');
                } else {
                    clearTagsBtn.classList.add('hidden');
                }

                const filtered = LIBRARY_INDEX.filter(item => {
                    const matchesText = item.search_text.includes(query);
                    if (!matchesText) return false;

                    if (selectedTags.length === 0) return true;

                    const itemTags = item.tags || [];
                    const intersection = selectedTags.filter(t => itemTags.includes(t));

                    switch (operator) {
                        case 'OR': return intersection.length > 0;
                        case 'AND': return intersection.length === selectedTags.length;
                        case 'XOR': return intersection.length === 1;
                        case 'NOR': return intersection.length === 0;
                        default: return true;
                    }
                });

                renderGrid(filtered);

                if (filtered.length === 0) {
                    noResults.classList.remove('hidden');
                } else {
                    noResults.classList.add('hidden');
                }
                
                countLabel.classList.remove('hidden');
                if (query === '' && selectedTags.length === 0) {
                    countLabel.innerText = 'Showing all articles';
                } else {
                    countLabel.innerText = `Found ${filtered.length} matching articles`;
                }
            }

            renderGrid(LIBRARY_INDEX);
        }

        function renderGrid(items) {
            const grid = document.getElementById('articles-grid');
            grid.innerHTML = '';

            items.forEach(item => {
                const card = document.createElement('div');
                card.className = "flex flex-col h-full bg-white border border-stone-100 p-6 rounded-sm shadow-sm hover:shadow-md hover:border-stone-300 transition duration-300 cursor-pointer group";
                card.onclick = () => window.location.href = `?article=${item.slug}`;

                let tagsHTML = '';
                if (item.tags && item.tags.length > 0) {
                    tagsHTML = `<div class="mt-3 flex flex-wrap gap-1">
                        ${item.tags.slice(0,3).map(t => `<span class="text-[10px] uppercase tracking-wider px-2 py-0.5 bg-stone-100 text-stone-500 rounded-sm">${t}</span>`).join('')}
                        ${item.tags.length > 3 ? `<span class="text-[10px] text-stone-400">+${item.tags.length - 3}</span>` : ''}
                    </div>`;
                }

                card.innerHTML = `
                    <h2 class="text-xl font-bold text-stone-800 mb-2 group-hover:text-red-800 transition">${item.title}</h2>
                    <p class="text-xs uppercase tracking-widest text-stone-500 mb-2 ui-font">By ${item.author}</p>
                    ${tagsHTML}
                    <div class="h-2"></div>
                    <p class="text-stone-600 font-serif leading-relaxed text-sm flex-grow">${item.snippet}</p>
                    <div class="mt-4 pt-4 border-t border-stone-100 text-right">
                        <span class="text-xs font-bold text-stone-400 uppercase tracking-wide group-hover:text-stone-600 transition">Read Article &rarr;</span>
                    </div>
                `;
                grid.appendChild(card);
            });
        }

        // --- 5. Article Loader Logic ---
        async function loadArticle(slug) {
            try {
                const response = await fetch(`articles/${slug}.xml`);
                if (!response.ok) throw new Error("Article not found");
                const xmlString = await response.text();
                parseAndRender(xmlString);
            } catch (e) {
                console.error("Failed to load article:", e);
                render404("404 Not Found", "The requested article could not be located in our archives.");
            }
        }

        function render404(title, message) {
            document.getElementById('article-header').innerHTML = `
                <h1 class="text-4xl lg:text-5xl font-bold text-stone-900 mb-4 leading-tight">${title}</h1>
                <div class="h-1 w-20 bg-red-800"></div>
            `;
            
            document.getElementById('article-body').innerHTML = `
                <p class="text-xl text-stone-600 mb-6 font-serif">${message}</p>
                <div class="p-6 bg-stone-100 border-l-4 border-stone-300 rounded-r-sm">
                    <p class="text-sm text-stone-500 ui-font">
                        <strong>Tip:</strong> Try <a href="index.php" class="underline text-red-800">returning to the library</a> to search for the correct article.
                    </p>
                </div>
            `;
            document.getElementById('sidebar-related').classList.add('hidden');
            document.getElementById('sidebar-references').classList.add('hidden');
        }

        function parseAndRender(xmlString) {
            const parser = new DOMParser();
            const xmlDoc = parser.parseFromString(xmlString, "text/xml");
            const foundVerses = new Set();

            const title = xmlDoc.querySelector("title").textContent;
            const author = xmlDoc.querySelector("author").textContent;
            const tags = xmlDoc.querySelectorAll("tags > tag");
            let tagsHTML = "";
            if (tags.length > 0) {
                tagsHTML = `<div class="flex gap-2 mt-2">
                    ${Array.from(tags).map(t => `<span class="text-xs border border-stone-200 px-2 py-0.5 rounded-sm text-stone-500">${t.textContent}</span>`).join('')}
                </div>`;
            }

            document.getElementById('article-header').innerHTML = `
                <h1 class="text-4xl lg:text-5xl font-bold text-stone-900 mb-4 leading-tight">${title}</h1>
                <div class="flex flex-col space-y-2">
                    <div class="flex items-center space-x-2 text-stone-500 ui-font text-sm uppercase tracking-wide">
                        <span>By ${author}</span>
                    </div>
                    ${tagsHTML}
                </div>
            `;

            const paragraphs = xmlDoc.querySelectorAll("body > paragraph");
            let bodyHTML = "";
            paragraphs.forEach(p => {
                let text = p.innerHTML.trim();

                // Replace custom XML tags with corresponding HTML tags
                text = text.replace(/<bold>(.*?)<\/bold>/g, '<strong>$1</strong>');
                text = text.replace(/<italics>(.*?)<\/italics>/g, '<em>$1</em>');
                text = text.replace(/<underline>(.*?)<\/underline>/g, '<u>$1</u>');

                text = linkifyBibleVerses(text, foundVerses);
                bodyHTML += `<p class="mb-6">${text}</p>`;
            });
            document.getElementById('article-body').innerHTML = bodyHTML;

            const relatedItems = xmlDoc.querySelectorAll("related > item");
            if (relatedItems.length > 0) {
                const list = document.getElementById('related-list');
                document.getElementById('sidebar-related').classList.remove('hidden');
                relatedItems.forEach(item => {
                    const li = document.createElement('li');
                    li.innerHTML = `<a href="?article=${item.getAttribute('slug')}" class="block p-3 bg-white border border-stone-200 shadow-sm hover:border-stone-400 hover:shadow-md transition duration-200 rounded-sm" data-related-slug="${item.getAttribute('slug')}">
                        <span class="font-serif text-stone-800">${item.textContent}</span>
                    </a>`;
                    list.appendChild(li);
                });
                resolveRelatedTitles(); // Ensure titles are resolved after adding related items
            }

            const manualRefs = xmlDoc.querySelectorAll("references > link, references > bible");
            const list = document.getElementById('reference-list');
            list.innerHTML = ''; 
            const existingRefTexts = new Set();
            const versionSelector = document.getElementById('bible-version-selector');
            const selectedVersion = versionSelector ? versionSelector.value : 'ESV';

            if (manualRefs.length > 0 || foundVerses.size > 0) {
                document.getElementById('sidebar-references').classList.remove('hidden');
                
                manualRefs.forEach(ref => {
                    const refText = ref.textContent.trim();
                    if (!existingRefTexts.has(refText.toLowerCase())) {
                        existingRefTexts.add(refText.toLowerCase());
                        const li = document.createElement('li');
                        if (ref.tagName === 'link') {
                            li.innerHTML = `<a href="${ref.getAttribute('url')}" target="_blank" class="flex items-center hover:text-stone-900 group">
                                <span class="w-2 h-2 bg-stone-300 rounded-full mr-3 group-hover:bg-red-800 transition"></span>
                                <span class="underline decoration-stone-300 underline-offset-4 group-hover:decoration-red-800">${ref.textContent}</span>
                            </a>`;
                        } else if (ref.tagName === 'bible') {
                            const encodedRef = encodeURIComponent(refText);
                            li.innerHTML = `<a href="https://www.biblegateway.com/passage/?search=${encodedRef}&version=${selectedVersion}" target="_blank" class="flex items-center hover:text-stone-900 group">
                                <span class="w-2 h-2 bg-stone-300 rounded-full mr-3 group-hover:bg-red-800 transition"></span>
                                <span class="italic font-serif">${refText}</span>
                            </a>`;
                        }
                        list.appendChild(li);
                    }
                });

                foundVerses.forEach(verse => {
                    const normalizedVerse = verse.trim().toLowerCase();
                    if (!existingRefTexts.has(normalizedVerse)) {
                        existingRefTexts.add(normalizedVerse);
                        const li = document.createElement('li');
                        const encodedRef = encodeURIComponent(verse);
                        li.innerHTML = `<a href="https://www.biblegateway.com/passage/?search=${encodedRef}&version=${selectedVersion}" target="_blank" class="flex items-center hover:text-stone-900 group">
                            <span class="w-2 h-2 bg-stone-300 rounded-full mr-3 group-hover:bg-red-800 transition"></span>
                            <span class="italic font-serif">${verse}</span>
                        </a>`;
                        list.appendChild(li);
                    }
                });
            } else {
                document.getElementById('sidebar-references').classList.add('hidden');
            }

            initTooltips();
        }

        // --- 6. Utilities ---
        function linkifyBibleVerses(text, foundVersesSet = null) {
            const regex = /\b((?:1|2|3)?\s?[A-Za-z]+)\s(\d+):(\d+)(?:-(\d+))?/g;
            const versionSelector = document.getElementById('bible-version-selector');
            const selectedVersion = versionSelector ? versionSelector.value : 'ESV';

            return text.replace(regex, (match) => {
                if (foundVersesSet) {
                    foundVersesSet.add(match);
                }
                const encodedRef = encodeURIComponent(match);
                return `<a href="https://www.biblegateway.com/passage/?search=${encodedRef}&version=${selectedVersion}" target="_blank" class="bible-ref" data-ref="${match}">${match}</a>`;
            });
        }

        let hoverTimer;
        let isTooltipHovered = false;

        function initTooltips() {
            const links = document.querySelectorAll('.bible-ref');
            const tooltip = document.getElementById('bible-tooltip');
            const cache = {}; // Cache object to store API results
            let activeReference = null; // Track the currently active reference

            links.forEach(link => {
                link.addEventListener('mouseenter', (e) => {
                    const ref = link.getAttribute('data-ref');

                    // If hovering over the same reference, do nothing
                    if (activeReference === ref) return;

                    activeReference = ref;
                    clearTimeout(hoverTimer);

                    // Preload content immediately
                    preloadTooltip(ref, cache);

                    // Delay displaying the tooltip and updating content and position
                    hoverTimer = setTimeout(() => {
                        if (cache[ref]) {
                            updateTooltipContent(cache[ref]);
                            updateTooltipPosition(link, tooltip);
                        }
                        tooltip.classList.remove('hidden');
                        tooltip.classList.remove('fade-out');
                        tooltip.classList.add('fade-in');
                        requestAnimationFrame(() => {
                            tooltip.classList.add('tooltip-active');
                        });
                    }, 500);
                });

                link.addEventListener('mouseleave', () => {
                    setTimeout(() => {
                        if (!isTooltipHovered) {
                            hideTooltip();
                            activeReference = null; // Reset the active reference
                        }
                    }, 100);
                    clearTimeout(hoverTimer);
                });
            });

            tooltip.addEventListener('mouseenter', () => isTooltipHovered = true);
            tooltip.addEventListener('mouseleave', () => {
                isTooltipHovered = false;
                hideTooltip();
                activeReference = null; // Reset the active reference
            });
        }

        async function preloadTooltip(reference, cache) {
            // If the reference is already cached, skip the API call
            if (cache[reference]) {
                return;
            }

            try {
                // Get the selected Bible version
                const versionSelector = document.getElementById('bible-version-selector');
                const selectedVersion = versionSelector ? versionSelector.value : 'ESV';

                // Append the selected version to the API call
                const response = await fetch(`${API_ENDPOINT}${encodeURIComponent(reference)}&version=${selectedVersion}`);
                if (response.ok) {
                    const data = await response.json();
                    if (data && data.text) {
                        const combinedText = Object.entries(data.text)
                            .map(([verseNumber, verseText]) => `<strong>${verseNumber}</strong> ${verseText}`)
                            .join(' ');

                        const match = reference.match(/^(?<book>.+?)\s(?<chapter>\d+):(?<verseRange>\d+(?:-\d+)?)$/);
                        if (!match || !match.groups) {
                            throw new Error('Invalid reference format');
                        }

                        const { book, chapter, verseRange } = match.groups;
                        const [startVerse, endVerse] = verseRange.includes('-')
                            ? verseRange.split('-').map(Number)
                            : [Number(verseRange), Number(verseRange)];

                        const priorVerseRef = `${book} ${chapter}:${startVerse - 1}`;
                        const nextVerseRef = `${book} ${chapter}:${endVerse + 1}`;

                        const priorVersePromise = startVerse > 1
                            ? fetch(`${API_ENDPOINT}${encodeURIComponent(priorVerseRef)}&version=${selectedVersion}`).then(res => res.ok ? res.json() : null)
                            : Promise.resolve(null);

                        const nextVersePromise = fetch(`${API_ENDPOINT}${encodeURIComponent(nextVerseRef)}&version=${selectedVersion}`).then(res => res.ok ? res.json() : null);

                        const [priorVerseData, nextVerseData] = await Promise.all([priorVersePromise, nextVersePromise]);

                        let priorText = '';
                        if (priorVerseData && priorVerseData.text) {
                            priorText = Object.entries(priorVerseData.text)
                                .map(([verseNumber, verseText]) => `<span style='opacity: 0.5;'><strong>${verseNumber}</strong> ${verseText}</span>`)
                                .join(' ');
                        }

                        let nextText = '';
                        if (nextVerseData && nextVerseData.text) {
                            nextText = Object.entries(nextVerseData.text)
                                .map(([verseNumber, verseText]) => `<span style='opacity: 0.5;'><strong>${verseNumber}</strong> ${verseText}</span>`)
                                .join(' ');
                        }

                        // Cache the results
                        cache[reference] = {
                            mainText: combinedText,
                            priorText,
                            nextText,
                            reference
                        };
                    }
                }
            } catch (error) {
                console.error('Failed to fetch verse:', error);
                cache[reference] = {
                    error: true
                };
            }
        }

        function updateTooltipContent(cachedData) {
            const textContainer = document.getElementById('tooltip-verse-text');
            const refContainer = document.getElementById('tooltip-ref');

            if (cachedData.error) {
                textContainer.innerHTML = '<span class="text-red-800">Error loading verse.</span>';
                return;
            }

            textContainer.innerHTML = `${cachedData.priorText ? cachedData.priorText + '<br>' : ''}${cachedData.mainText}${cachedData.nextText ? '<br>' + cachedData.nextText : ''}`;
            refContainer.textContent = cachedData.reference;
        }

        function updateTooltipPosition(element, tooltip) {
            const mainContainer = document.getElementById('main-container');
            const elementRect = element.getBoundingClientRect();
            const containerRect = mainContainer.getBoundingClientRect();

            // Temporarily make the tooltip visible to measure its dimensions
            tooltip.style.display = 'block';
            const tooltipRect = tooltip.getBoundingClientRect();

            let top = elementRect.top - containerRect.top + element.offsetHeight + 90; // Adjusted to place slightly lower
            let left = elementRect.left - containerRect.left - 100; // Adjusted to align closer to the text

            // Ensure the tooltip stays within the viewport
            const viewportHeight = window.innerHeight;
            const viewportWidth = window.innerWidth;

            if (top + tooltipRect.height > viewportHeight) {
                top = elementRect.top - containerRect.top - tooltipRect.height + 78; // Position above the element
            }

            if (left + tooltipRect.width > viewportWidth) {
                left = viewportWidth - tooltipRect.width - 10; // Add some padding from the right
            }

            // Adjust tooltip to be relative to the main container
            tooltip.style.position = 'absolute';
            tooltip.style.top = `${top}px`;
            tooltip.style.left = `${left + containerRect.left}px`;
        }

        function hideTooltip() {
            const tooltip = document.getElementById('bible-tooltip');
            tooltip.classList.add('fade-out');
            tooltip.classList.remove('fade-in');
            setTimeout(() => {
                tooltip.classList.add('hidden');
                tooltip.classList.remove('tooltip-active');
            }, 500); // Match the animation duration
        }

        // Ensure dark mode styling is based on site preferences
        (function() {
            const selector = document.getElementById('bible-version-selector');
            const updateSelectorTheme = () => {
                if (document.body.classList.contains('dark-mode')) {
                    selector.classList.add('dark:text-stone-300', 'dark:bg-stone-800', 'dark:border-stone-600');
                } else {
                    selector.classList.remove('dark:text-stone-300', 'dark:bg-stone-800', 'dark:border-stone-600');
                }
            };

            // Initial check
            updateSelectorTheme();

            // Observe changes to the body class
            const observer = new MutationObserver(updateSelectorTheme);
            observer.observe(document.body, { attributes: true, attributeFilter: ['class'] });
        })();

        // --- CLIENT SIDE LOGIC ---
        function resolveRelatedTitles() {
            const relatedLinks = document.querySelectorAll('[data-related-slug]');
            relatedLinks.forEach(link => {
                const slug = link.getAttribute('data-related-slug');
                if (ARTICLE_TITLES[slug]) {
                    link.textContent = ARTICLE_TITLES[slug];
                }
            });
        }

        document.addEventListener('DOMContentLoaded', resolveRelatedTitles);
    </script>
</body>
</html>