<?php

return [
    [
        'name' => 'Punch',
        'domain' => 'punchng.com',
        'enabled' => true,
        'rss_urls' => [
            'https://punchng.com/feed',
        ],
        'list_urls' => [
            'https://punchng.com/topics/news/',
            'https://punchng.com/topics/metro-plus/',
        ],
        'allowed_path_hints' => ['/news/', '/metro/', '/crime/', '/2025/', '/2026/'],
        'blocked_path_hints' => ['/tag/', '/author/', '/category/', '/page/', '/video/', '/podcast/'],
    ],
    [
        'name' => 'Vanguard',
        'domain' => 'vanguardngr.com',
        'enabled' => true,
        'rss_urls' => [
            'https://www.vanguardngr.com/feed',
        ],
        'list_urls' => [
            'https://www.vanguardngr.com/category/national-news/',
            'https://www.vanguardngr.com/category/metro/',
        ],
        'allowed_path_hints' => ['/news/', '/metro/', '/2025/', '/2026/', '/category/'],
        'blocked_path_hints' => ['/tag/', '/author/', '/page/', '/amp/', '/video/'],
    ],
    [
        'name' => 'Premium Times',
        'domain' => 'premiumtimesng.com',
        'enabled' => true,
        'rss_urls' => [
            'https://www.premiumtimesng.com/feed',
        ],
        'list_urls' => [
            'https://www.premiumtimesng.com/news/headlines',
            'https://www.premiumtimesng.com/news/top-news',
        ],
        'allowed_path_hints' => ['/news/', '/regional/', '/2025/', '/2026/'],
        'blocked_path_hints' => ['/tag/', '/author/', '/page/', '/amp/', '/topics/'],
    ],
    [
        'name' => 'Nigerian Tribune',
        'domain' => 'tribuneonlineng.com',
        'enabled' => true,
        'rss_urls' => [
            'https://tribuneonlineng.com/feed',
        ],
        'list_urls' => [
            'https://tribuneonlineng.com/category/news/',
            'https://tribuneonlineng.com/category/metro/',
        ],
        'allowed_path_hints' => ['/news/', '/metro/', '/crime/', '/2025/', '/2026/'],
        'blocked_path_hints' => ['/tag/', '/author/', '/page/', '/amp/', '/gallery/'],
    ],
    [
        'name' => 'Daily Trust',
        'domain' => 'dailytrust.com',
        'enabled' => true,
        'rss_urls' => [
            'https://dailytrust.com/feed',
        ],
        'list_urls' => [
            'https://dailytrust.com/category/news/',
            'https://dailytrust.com/category/metro/',
        ],
        'allowed_path_hints' => ['/news/', '/metro/', '/city-news/', '/2025/', '/2026/'],
        'blocked_path_hints' => ['/tag/', '/author/', '/page/', '/amp/', '/videos/'],
    ],

    // National / General News
    [
        'name' => 'Daily Post',
        'domain' => 'dailypost.ng',
        'enabled' => true,
        'rss_urls' => [
            'https://dailypost.ng/feed',
        ],
        'list_urls' => [
            'https://dailypost.ng/news/',
            'https://dailypost.ng/category/metro/',
            'https://dailypost.ng/category/national-news/',
        ],
        'allowed_path_hints' => ['/news/', '/category/', '/metro/', '/national', '/2025/', '/2026/'],
        'blocked_path_hints' => ['/tag/', '/author/', '/page/', '/video/', '/about/'],
    ],
    [
        'name' => 'The Guardian Nigeria',
        'domain' => 'guardian.ng',
        'enabled' => true,
        'rss_urls' => [
            'https://guardian.ng/feed',
        ],
        'list_urls' => [
            'https://guardian.ng/category/news/',
            'https://guardian.ng/category/metro/',
            'https://guardian.ng/category/lead-story/',
        ],
        'allowed_path_hints' => ['/category/news/', '/metro/', '/lead-story/', '/2025/', '/2026/'],
        'blocked_path_hints' => ['/tag/', '/author/', '/page/', '/video/', '/gallery/'],
    ],
    [
        'name' => 'ThisDay',
        'domain' => 'thisdaylive.com',
        'enabled' => true,
        'rss_urls' => [
            'https://www.thisdaylive.com/index.php/feed',
        ],
        'list_urls' => [
            'https://www.thisdaylive.com/index.php/category/news/',
            'https://www.thisdaylive.com/index.php/category/nigeria/',
        ],
        'allowed_path_hints' => ['/category/news/', '/category/nigeria/', '/2025/', '/2026/'],
        'blocked_path_hints' => ['/tag/', '/author/', '/page/', '/video/', '/cartoon/'],
    ],
    [
        'name' => 'Leadership',
        'domain' => 'leadership.ng',
        'enabled' => true,
        'rss_urls' => [
            'https://leadership.ng/feed',
        ],
        'list_urls' => [
            'https://leadership.ng/category/news/',
            'https://leadership.ng/category/metro/',
            'https://leadership.ng/category/crime/',
        ],
        'allowed_path_hints' => ['/category/news/', '/metro/', '/crime/', '/2025/', '/2026/'],
        'blocked_path_hints' => ['/tag/', '/author/', '/page/', '/video/', '/podcast/'],
    ],
    [
        'name' => 'The Nation',
        'domain' => 'thenationonlineng.net',
        'enabled' => true,
        'rss_urls' => [
            'https://thenationonlineng.net/feed',
        ],
        'list_urls' => [
            'https://thenationonlineng.net/category/news/',
            'https://thenationonlineng.net/category/metro/',
        ],
        'allowed_path_hints' => ['/category/news/', '/metro/', '/2025/', '/2026/'],
        'blocked_path_hints' => ['/tag/', '/author/', '/page/', '/video/', '/opinion/'],
    ],
    [
        'name' => 'The Sun Nigeria',
        'domain' => 'sunnewsonline.com',
        'enabled' => true,
        'rss_urls' => [
            'https://sunnewsonline.com/feed',
        ],
        'list_urls' => [
            'https://sunnewsonline.com/category/national/',
            'https://sunnewsonline.com/category/metro/',
        ],
        'allowed_path_hints' => ['/category/national/', '/metro/', '/2025/', '/2026/'],
        'blocked_path_hints' => ['/tag/', '/author/', '/page/', '/video/', '/column/'],
    ],
    [
        'name' => 'BusinessDay Nigeria',
        'domain' => 'businessday.ng',
        'enabled' => true,
        'rss_urls' => [
            'https://businessday.ng/feed',
        ],
        'list_urls' => [
            'https://businessday.ng/news/',
            'https://businessday.ng/category/news/',
        ],
        'allowed_path_hints' => ['/news/', '/category/news/', '/2025/', '/2026/'],
        'blocked_path_hints' => ['/tag/', '/author/', '/page/', '/video/', '/opinion/'],
    ],
    [
        'name' => 'Sahara Reporters',
        'domain' => 'saharareporters.com',
        'enabled' => true,
        'rss_urls' => [
            'https://saharareporters.com/articles/rss-feed',
        ],
        'list_urls' => [
            'https://saharareporters.com/latest',
            'https://saharareporters.com/news',
        ],
        'allowed_path_hints' => ['/news/', '/latest/', '/2025/', '/2026/'],
        'blocked_path_hints' => ['/tag/', '/author/', '/page/', '/video/', '/live/'],
    ],
    [
        'name' => 'TheCable',
        'domain' => 'thecable.ng',
        'enabled' => true,
        'rss_urls' => [
            'https://www.thecable.ng/feed',
        ],
        'list_urls' => [
            'https://www.thecable.ng/category/news/',
            'https://www.thecable.ng/category/metro/',
        ],
        'allowed_path_hints' => ['/category/news/', '/metro/', '/2025/', '/2026/'],
        'blocked_path_hints' => ['/tag/', '/author/', '/page/', '/video/', '/podcast/'],
    ],
    [
        'name' => 'Ripples Nigeria',
        'domain' => 'ripplesnigeria.com',
        'enabled' => true,
        'rss_urls' => [
            'https://ripplesnigeria.com/feed',
        ],
        'list_urls' => [
            'https://ripplesnigeria.com/category/news/',
            'https://ripplesnigeria.com/category/metro/',
        ],
        'allowed_path_hints' => ['/category/news/', '/metro/', '/2025/', '/2026/'],
        'blocked_path_hints' => ['/tag/', '/author/', '/page/', '/video/', '/opinion/'],
    ],
    [
        'name' => 'The ICIR',
        'domain' => 'icirnigeria.org',
        'enabled' => true,
        'rss_urls' => [
            'https://www.icirnigeria.org/feed',
        ],
        'list_urls' => [
            'https://www.icirnigeria.org/category/news/',
            'https://www.icirnigeria.org/category/investigation/',
        ],
        'allowed_path_hints' => ['/category/news/', '/investigation/', '/2025/', '/2026/'],
        'blocked_path_hints' => ['/tag/', '/author/', '/page/', '/video/', '/about/'],
    ],
    [
        'name' => 'Peoples Gazette',
        'domain' => 'gazettengr.com',
        'enabled' => true,
        'rss_urls' => [
            'https://gazettengr.com/feed',
        ],
        'list_urls' => [
            'https://gazettengr.com/category/news/',
            'https://gazettengr.com/category/nigeria/',
        ],
        'allowed_path_hints' => ['/category/news/', '/category/nigeria/', '/2025/', '/2026/'],
        'blocked_path_hints' => ['/tag/', '/author/', '/page/', '/video/', '/opinion/'],
    ],
    [
        'name' => 'Blueprint',
        'domain' => 'blueprint.ng',
        'enabled' => true,
        'rss_urls' => [
            'https://blueprint.ng/feed',
        ],
        'list_urls' => [
            'https://blueprint.ng/category/news/',
            'https://blueprint.ng/category/metro/',
        ],
        'allowed_path_hints' => ['/category/news/', '/metro/', '/2025/', '/2026/'],
        'blocked_path_hints' => ['/tag/', '/author/', '/page/', '/video/', '/editorial/'],
    ],
    [
        'name' => 'Independent Nigeria',
        'domain' => 'independent.ng',
        'enabled' => true,
        'rss_urls' => [
            'https://independent.ng/feed',
        ],
        'list_urls' => [
            'https://independent.ng/category/news/',
            'https://independent.ng/category/metro/',
        ],
        'allowed_path_hints' => ['/category/news/', '/metro/', '/2025/', '/2026/'],
        'blocked_path_hints' => ['/tag/', '/author/', '/page/', '/video/', '/opinion/'],
    ],
    [
        'name' => 'News Agency of Nigeria',
        'domain' => 'nannews.ng',
        'enabled' => true,
        'rss_urls' => [
            'https://nannews.ng/feed',
        ],
        'list_urls' => [
            'https://nannews.ng/category/news/',
            'https://nannews.ng/category/top-stories/',
        ],
        'allowed_path_hints' => ['/category/news/', '/top-stories/', '/2025/', '/2026/'],
        'blocked_path_hints' => ['/tag/', '/author/', '/page/', '/video/', '/about/'],
    ],

    // Regional / Local News
    [
        'name' => 'Legit.ng',
        'domain' => 'legit.ng',
        'enabled' => true,
        'rss_urls' => [
            'https://www.legit.ng/rss/all.rss',
        ],
        'list_urls' => [
            'https://www.legit.ng/nigeria/',
            'https://www.legit.ng/latest/',
        ],
        'allowed_path_hints' => ['/nigeria/', '/latest/', '/2025/', '/2026/'],
        'blocked_path_hints' => ['/tag/', '/author/', '/page/', '/video/', '/quizzes/'],
    ],
    [
        'name' => 'Naija News',
        'domain' => 'naijanews.com',
        'enabled' => true,
        'rss_urls' => [
            'https://www.naijanews.com/feed',
        ],
        'list_urls' => [
            'https://www.naijanews.com/news/',
            'https://www.naijanews.com/category/nigeria-news/',
        ],
        'allowed_path_hints' => ['/news/', '/category/nigeria-news/', '/2025/', '/2026/'],
        'blocked_path_hints' => ['/tag/', '/author/', '/page/', '/video/', '/entertainment/'],
    ],
    [
        'name' => 'PM News Nigeria',
        'domain' => 'pmnewsnigeria.com',
        'enabled' => true,
        'rss_urls' => [
            'https://pmnewsnigeria.com/feed',
        ],
        'list_urls' => [
            'https://pmnewsnigeria.com/category/news/',
            'https://pmnewsnigeria.com/category/metro/',
        ],
        'allowed_path_hints' => ['/category/news/', '/metro/', '/2025/', '/2026/'],
        'blocked_path_hints' => ['/tag/', '/author/', '/page/', '/video/', '/sport/'],
    ],
    [
        'name' => 'Information Nigeria',
        'domain' => 'informationng.com',
        'enabled' => true,
        'rss_urls' => [
            'https://www.informationng.com/feed',
        ],
        'list_urls' => [
            'https://www.informationng.com/news',
            'https://www.informationng.com/category/general',
        ],
        'allowed_path_hints' => ['/news', '/category/general', '/2025/', '/2026/'],
        'blocked_path_hints' => ['/tag/', '/author/', '/page/', '/video/', '/entertainment/'],
    ],
    [
        'name' => 'Daily Nigerian',
        'domain' => 'dailynigerian.com',
        'enabled' => true,
        'rss_urls' => [
            'https://dailynigerian.com/feed',
        ],
        'list_urls' => [
            'https://dailynigerian.com/category/news/',
            'https://dailynigerian.com/category/metro/',
        ],
        'allowed_path_hints' => ['/category/news/', '/metro/', '/2025/', '/2026/'],
        'blocked_path_hints' => ['/tag/', '/author/', '/page/', '/video/', '/opinion/'],
    ],
    [
        'name' => 'Eagle Online',
        'domain' => 'eagleonline.com.ng',
        'enabled' => true,
        'rss_urls' => [
            'https://eagleonline.com.ng/feed',
        ],
        'list_urls' => [
            'https://eagleonline.com.ng/category/news/',
            'https://eagleonline.com.ng/category/crime/',
        ],
        'allowed_path_hints' => ['/category/news/', '/crime/', '/2025/', '/2026/'],
        'blocked_path_hints' => ['/tag/', '/author/', '/page/', '/video/', '/entertainment/'],
    ],
    [
        'name' => 'News Diary Online',
        'domain' => 'newsdiaryonline.com',
        'enabled' => true,
        'rss_urls' => [
            'https://newsdiaryonline.com/feed',
        ],
        'list_urls' => [
            'https://newsdiaryonline.com/category/news/',
            'https://newsdiaryonline.com/category/security/',
        ],
        'allowed_path_hints' => ['/category/news/', '/security/', '/2025/', '/2026/'],
        'blocked_path_hints' => ['/tag/', '/author/', '/page/', '/video/', '/features/'],
    ],
    [
        'name' => 'Naija247News',
        'domain' => 'naija247news.com',
        'enabled' => true,
        'rss_urls' => [
            'https://naija247news.com/feed',
        ],
        'list_urls' => [
            'https://naija247news.com/category/news/',
            'https://naija247news.com/category/metro/',
        ],
        'allowed_path_hints' => ['/category/news/', '/metro/', '/2025/', '/2026/'],
        'blocked_path_hints' => ['/tag/', '/author/', '/page/', '/video/', '/business/'],
    ],
    [
        'name' => 'The Trent Online',
        'domain' => 'thetrentonline.com',
        'enabled' => true,
        'rss_urls' => [
            'https://thetrentonline.com/feed',
        ],
        'list_urls' => [
            'https://thetrentonline.com/category/news/',
            'https://thetrentonline.com/category/nigeria/',
        ],
        'allowed_path_hints' => ['/category/news/', '/category/nigeria/', '/2025/', '/2026/'],
        'blocked_path_hints' => ['/tag/', '/author/', '/page/', '/video/', '/entertainment/'],
    ],

    // Emergency / Agency Sources
    [
        'name' => 'Nigeria Police Force',
        'domain' => 'npf.gov.ng',
        'enabled' => true,
        'list_urls' => [
            'https://npf.gov.ng/news/',
            'https://npf.gov.ng/press-release/',
        ],
        'allowed_path_hints' => ['/news/', '/press', '/2025/', '/2026/'],
        'blocked_path_hints' => ['/tag/', '/author/', '/page/', '/gallery/', '/about/'],
    ],
    [
        'name' => 'Federal Road Safety Corps',
        'domain' => 'frsc.gov.ng',
        'enabled' => true,
        'list_urls' => [
            'https://frsc.gov.ng/category/news/',
            'https://frsc.gov.ng/category/press-release/',
        ],
        'allowed_path_hints' => ['/news/', '/press', '/2025/', '/2026/'],
        'blocked_path_hints' => ['/tag/', '/author/', '/page/', '/gallery/', '/about/'],
    ],
    [
        'name' => 'National Emergency Management Agency',
        'domain' => 'nema.gov.ng',
        'enabled' => true,
        'list_urls' => [
            'https://nema.gov.ng/category/news/',
            'https://nema.gov.ng/category/press-release/',
        ],
        'allowed_path_hints' => ['/news/', '/press', '/2025/', '/2026/'],
        'blocked_path_hints' => ['/tag/', '/author/', '/page/', '/gallery/', '/about/'],
    ],
    [
        'name' => 'Nigeria Security and Civil Defence Corps',
        'domain' => 'nscdc.gov.ng',
        'enabled' => true,
        'list_urls' => [
            'https://nscdc.gov.ng/news/',
            'https://nscdc.gov.ng/press-release/',
        ],
        'allowed_path_hints' => ['/news/', '/press', '/2025/', '/2026/'],
        'blocked_path_hints' => ['/tag/', '/author/', '/page/', '/gallery/', '/about/'],
    ],
    [
        'name' => 'National Drug Law Enforcement Agency',
        'domain' => 'ndlea.gov.ng',
        'enabled' => true,
        'list_urls' => [
            'https://ndlea.gov.ng/category/news/',
            'https://ndlea.gov.ng/category/press-release/',
        ],
        'allowed_path_hints' => ['/news/', '/press', '/2025/', '/2026/'],
        'blocked_path_hints' => ['/tag/', '/author/', '/page/', '/gallery/', '/about/'],
    ],
    [
        'name' => 'Nigerian Army',
        'domain' => 'army.mil.ng',
        'enabled' => true,
        'list_urls' => [
            'https://army.mil.ng/news/',
            'https://army.mil.ng/press-release/',
        ],
        'allowed_path_hints' => ['/news/', '/press', '/2025/', '/2026/'],
        'blocked_path_hints' => ['/tag/', '/author/', '/page/', '/gallery/', '/about/'],
    ],
    [
        'name' => 'Nigerian Navy',
        'domain' => 'navy.mil.ng',
        'enabled' => true,
        'list_urls' => [
            'https://navy.mil.ng/news/',
            'https://navy.mil.ng/press-release/',
        ],
        'allowed_path_hints' => ['/news/', '/press', '/2025/', '/2026/'],
        'blocked_path_hints' => ['/tag/', '/author/', '/page/', '/gallery/', '/about/'],
    ],
    [
        'name' => 'Nigerian Air Force',
        'domain' => 'airforce.mil.ng',
        'enabled' => true,
        'list_urls' => [
            'https://airforce.mil.ng/news/',
            'https://airforce.mil.ng/press-release/',
        ],
        'allowed_path_hints' => ['/news/', '/press', '/2025/', '/2026/'],
        'blocked_path_hints' => ['/tag/', '/author/', '/page/', '/gallery/', '/about/'],
    ],
    [
        'name' => 'Lagos State Emergency Management Agency',
        'domain' => 'lasema.gov.ng',
        'enabled' => true,
        'list_urls' => [
            'https://lasema.gov.ng/news/',
            'https://lasema.gov.ng/press-release/',
        ],
        'allowed_path_hints' => ['/news/', '/press', '/2025/', '/2026/'],
        'blocked_path_hints' => ['/tag/', '/author/', '/page/', '/gallery/', '/about/'],
    ],
    [
        'name' => 'Lagos State Traffic Management Authority',
        'domain' => 'lastma.gov.ng',
        'enabled' => true,
        'list_urls' => [
            'https://lastma.gov.ng/news/',
            'https://lastma.gov.ng/category/press-release/',
        ],
        'allowed_path_hints' => ['/news/', '/press', '/2025/', '/2026/'],
        'blocked_path_hints' => ['/tag/', '/author/', '/page/', '/gallery/', '/about/'],
    ],

    // Update note:
    // Added 34 new sources.
    // Skipped listed entries that do not currently fit website/list-page scraping reliably:
    // Arise.tv/Arise News, AIT News/Africa Independent Television, TVC News, Silverbird Television,
    // NTA News, Plus TV Africa, Galaxy TV, Wazobia TV, Rave TV, Lagos Television, Ben TV London,
    // TOS TV Network, News Central TV, Department of State Services, Nigerian Immigration Service,
    // Rivers State Emergency Management Agency, Kano State Fire Service, Abuja Environmental Protection Board,
    // Nairaland, Nigerian Observer, Western Post Nigeria, Metro Watch Nigeria, Igbere TV,
    // Nigeria Newsdesk, Tori.ng, Gistmania, NaijaTimes, InsideMainland, Instablog9ja,
    // Linda Ikeji Blog, PH City Blog, Lagos Traffic Reports, Abuja City Journal, Kano City Blog,
    // Warri City Blog, Aba City Blog, Benin City Blog, Jos City Watch, Calabar Blog.
];

