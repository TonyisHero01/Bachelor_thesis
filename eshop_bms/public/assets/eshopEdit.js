
/**
 * Previews selected image files inside the image container.
 *
 * @param {Event} event - File input change event.
 */
function previewImages(event) {
    const files = event.target.files;
    const previewContainer = document.getElementById('image_container');

    for (let i = 0; i < files.length; i += 1) {
        const file = files[i];

        if (!file.type.startsWith('image/')) {
            continue;
        }

        const img = document.createElement('img');
        img.file = file;
        img.width = 304;
        img.height = 228;

        previewContainer.appendChild(img);

        const reader = new FileReader();
        reader.onload = (function (aImg) {
            return function (e) {
                aImg.src = e.target.result;
            };
        }(img));

        reader.readAsDataURL(file);
    }
}

/**
 * Uploads a logo file and updates the logo preview.
 *
 * @param {Event} event - File input change event.
 */
function handleLogoUpload(event) {
    const file = event.target.files[0];
    const formData = new FormData();
    formData.append('logo', file);

    fetch('/logo_save', {
        method: 'POST',
        body: formData,
    })
        .then((response) => response.json())
        .then((data) => {
            if (data.filePath) {
                document.getElementById(
                    'logo_preview',
                ).src = `/images/${data.filePath}`;
            }
        })
        .catch((error) => console.error('上传错误:', error));
}

/**
 * Uploads selected image files to the server.
 *
 * @param {Event} event - File input change event.
 */
const handleImageUpload = (event) => {
    const files = event.target.files;
    const formData = new FormData();

    for (let i = 0; i < files.length; i += 1) {
        formData.append('images[]', files[i]);
    }

    fetch('/image_save', {
        method: 'POST',
        body: formData,
    })
        .then((response) => response.text())
        .then((text) => {
            try {
                JSON.parse(text);
            } catch (e) {
                console.error('Invalid JSON response');
            }
        })
        .catch((error) => {
            console.error('Error uploading images:', error);
        });
};

const imageUploadInput = document.querySelector(
    '.image_upload_input',
);

if (imageUploadInput) {
    imageUploadInput.addEventListener(
        'change',
        previewImages,
    );

    imageUploadInput.addEventListener(
        'change',
        handleImageUpload,
    );
}

/**
 * Deletes an image by name and removes it from the DOM.
 *
 * @param {string} imageName - Image file name.
 */
function deleteImage(imageName) {
    imageName = imageName.replace('images/', '');

    fetch(`/delete_cimage/${imageName}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
    })
        .then((response) => response.json())
        .then((data) => {
            if (data.status === 'Success') {
                const imageSection = document.querySelector(
                    `[data-image-url="${imageName}"]`,
                );
                if (imageSection) {
                    imageSection.remove();
                }
            } else {
                console.error('Failed to delete image: ', data.message);
            }
        })
        .catch((error) => console.error('Error:', error));
}

const searchConfigRoot = document.getElementById(
    'search_config_root',
);

let searchMethodSelect = null;

let searchConfigs = {};
let currentSearchMethod = 'lexical';

if (searchConfigRoot) {
    currentSearchMethod = String(
        searchConfigRoot.dataset.activeMethod || 'lexical',
    );

    try {
        searchConfigs = JSON.parse(
            searchConfigRoot.dataset.searchConfigs || '{}',
        );
    } catch (error) {
        console.error(
            'Failed to parse search configuration data:',
            error,
        );

        searchConfigs = {};
    }
}

function getObject(value) {
    if (
        value
        && typeof value === 'object'
        && !Array.isArray(value)
    ) {
        return value;
    }

    return {};
}

function setInputValue(id, value) {
    const element = document.getElementById(id);

    if (
        element
        && value !== undefined
        && value !== null
    ) {
        element.value = String(value);
    }
}

function setCheckboxValue(id, value) {
    const element = document.getElementById(id);

    if (element) {
        element.checked = Boolean(value);
    }
}

function readInteger(id, fallback) {
    const element = document.getElementById(id);
    const value = Number.parseInt(
        String(element?.value ?? ''),
        10,
    );

    return Number.isFinite(value)
        ? value
        : fallback;
}

function readFloat(id, fallback) {
    const element = document.getElementById(id);
    const value = Number.parseFloat(
        String(element?.value ?? ''),
    );

    return Number.isFinite(value)
        ? value
        : fallback;
}

function readCheckbox(id, fallback = false) {
    const element = document.getElementById(id);

    if (!element) {
        return fallback;
    }

    return Boolean(element.checked);
}

function showSearchMethodSettings(method) {
    const sections = document.querySelectorAll(
        '.search-method-settings',
    );

    sections.forEach((section) => {
        const sectionMethod = String(
            section.dataset.searchMethod || '',
        );

        const shouldShow = sectionMethod === method;

        section.hidden = !shouldShow;

        section.style.setProperty(
            'display',
            shouldShow ? 'block' : 'none',
            'important',
        );
    });
}

function loadCommonSearchConfig(config, method) {
    const defaultNames = {
        lexical: 'Lexical configuration',
        semantic_vector: 'Semantic vector configuration',
        elasticsearch_bm25:
            'Elasticsearch BM25 configuration',
    };

    setInputValue(
        'search_config_name',
        config.name
            ?? defaultNames[method]
            ?? 'Search configuration',
    );

    setInputValue(
        'name_weight',
        config.nameWeight ?? 20,
    );

    setInputValue(
        'description_weight',
        config.descriptionWeight ?? 5,
    );

    setInputValue(
        'category_weight',
        config.categoryWeight ?? 4,
    );

    setInputValue(
        'material_weight',
        config.materialWeight ?? 2,
    );

    setInputValue(
        'color_weight',
        config.colorWeight ?? 2,
    );

    setInputValue(
        'size_weight',
        config.sizeWeight ?? 2,
    );

    setInputValue(
        'attributes_weight',
        config.attributesWeight ?? 2,
    );

    setInputValue(
        'same_category_bonus',
        config.sameCategoryBonus ?? 0.35,
    );

    setInputValue(
        'same_material_bonus',
        config.sameMaterialBonus ?? 0.15,
    );

    setInputValue(
        'same_color_bonus',
        config.sameColorBonus ?? 0.10,
    );

    setInputValue(
        'same_size_bonus',
        config.sameSizeBonus ?? 0.10,
    );

    setInputValue(
        'same_category_recommendation_weight',
        config.sameCategoryRecommendationWeight ?? 0.35,
    );

    setInputValue(
        'same_color_recommendation_weight',
        config.sameColorRecommendationWeight ?? 0.10,
    );

    setInputValue(
        'same_size_recommendation_weight',
        config.sameSizeRecommendationWeight ?? 0.10,
    );

    setInputValue(
        'wishlist_recommendation_weight',
        config.wishlistRecommendationWeight ?? 0.30,
    );

    setInputValue(
        'order_history_recommendation_weight',
        config.orderHistoryRecommendationWeight ?? 0.25,
    );

    setInputValue(
        'search_history_recommendation_weight',
        config.searchHistoryRecommendationWeight ?? 0.20,
    );

    setInputValue(
        'view_history_recommendation_weight',
        config.viewHistoryRecommendationWeight ?? 0.35,
    );

    setInputValue(
        'max_recommendation_per_category',
        config.maxRecommendationPerCategory ?? 4,
    );

    setInputValue(
        'recommendation_diversity_penalty',
        config.recommendationDiversityPenalty ?? 0.10,
    );

    setCheckboxValue(
        'recommendation_enabled',
        config.recommendationEnabled ?? true,
    );

    setCheckboxValue(
        'recommendation_logging_enabled',
        config.recommendationLoggingEnabled ?? true,
    );
}

function loadLexicalSettings(config) {
    const settings = getObject(
        config.algorithmSettings,
    );

    const vectorizer = getObject(
        settings.vectorizer,
    );

    const candidateFilter = getObject(
        settings.candidate_filter,
    );

    const partialMatch = getObject(
        settings.partial_match,
    );

    const session = getObject(
        settings.session_recommendation,
    );

    const ngramRange = Array.isArray(
        vectorizer.ngram_range,
    )
        ? vectorizer.ngram_range
        : [1, 2];

    setCheckboxValue(
        'lexical_lowercase',
        vectorizer.lowercase ?? true,
    );

    setInputValue(
        'lexical_ngram_min',
        ngramRange[0] ?? 1,
    );

    setInputValue(
        'lexical_ngram_max',
        ngramRange[1] ?? 2,
    );

    setInputValue(
        'lexical_n_features',
        vectorizer.n_features ?? 262144,
    );

    setCheckboxValue(
        'lexical_alternate_sign',
        vectorizer.alternate_sign ?? false,
    );

    setInputValue(
        'lexical_normalization',
        vectorizer.normalization ?? 'l2',
    );

    setInputValue(
        'lexical_token_pattern',
        vectorizer.token_pattern ?? '\\b\\w+\\b',
    );

    setInputValue(
        'lexical_candidate_minimum_query_token_matches',
        candidateFilter.minimum_query_token_matches ?? 1,
    );

    setCheckboxValue(
        'lexical_fallback_to_all_documents',
        candidateFilter.fallback_to_all_documents ?? true,
    );

    setCheckboxValue(
        'lexical_require_all_query_tokens',
        partialMatch.require_all_query_tokens ?? true,
    );

    setInputValue(
        'lexical_partial_minimum_query_token_matches',
        partialMatch.minimum_query_token_matches ?? 1,
    );

    setInputValue(
        'lexical_partial_base_score',
        partialMatch.base_score ?? 1.0,
    );

    setInputValue(
        'lexical_partial_merge_bonus_weight',
        partialMatch.merge_bonus_weight ?? 0.20,
    );

    loadSessionRecommendationSettings(
        'lexical',
        session,
        3,
    );
}

function loadSemanticSettings(config) {
    const settings = getObject(
        config.algorithmSettings,
    );

    const documentFields = getObject(
        settings.document_fields,
    );

    const embedding = getObject(
        settings.embedding,
    );

    const reranking = getObject(
        settings.reranking,
    );

    const candidatePool = getObject(
        settings.candidate_pool,
    );

    const vectorSearch = getObject(
        settings.vector_search,
    );

    const session = getObject(
        settings.session_recommendation,
    );

    setCheckboxValue(
        'semantic_document_name',
        documentFields.name ?? true,
    );

    setCheckboxValue(
        'semantic_document_category',
        documentFields.category ?? true,
    );

    setCheckboxValue(
        'semantic_document_description',
        documentFields.description ?? true,
    );

    setCheckboxValue(
        'semantic_document_material',
        documentFields.material ?? true,
    );

    setCheckboxValue(
        'semantic_document_color',
        documentFields.color ?? true,
    );

    setCheckboxValue(
        'semantic_document_size',
        documentFields.size ?? true,
    );

    setCheckboxValue(
        'semantic_document_attributes',
        documentFields.attributes ?? false,
    );

    setInputValue(
        'semantic_embedding_batch_size',
        embedding.batch_size ?? 32,
    );

    setCheckboxValue(
        'semantic_normalize_embeddings',
        embedding.normalize_embeddings ?? true,
    );

    setInputValue(
        'semantic_similarity_weight',
        reranking.semantic_similarity_weight ?? 0.75,
    );

    setInputValue(
        'semantic_lexical_overlap_weight',
        reranking.lexical_overlap_weight ?? 0.25,
    );

    setInputValue(
        'semantic_minimum_token_length',
        reranking.minimum_token_length ?? 2,
    );

    setInputValue(
        'semantic_candidate_multiplier',
        candidatePool.multiplier ?? 5,
    );

    setInputValue(
        'semantic_minimum_candidates',
        candidatePool.minimum_candidates ?? 50,
    );

    setInputValue(
        'semantic_ivfflat_probes',
        vectorSearch.ivfflat_probes ?? 10,
    );

    loadSessionRecommendationSettings(
        'semantic',
        session,
        2,
    );
}

function loadElasticSettings(config) {
    const settings = getObject(
        config.algorithmSettings,
    );

    const searchQuery = getObject(
        settings.search_query,
    );

    const searchWeights = getObject(
        searchQuery.field_weights,
    );

    const recommendationQuery = getObject(
        settings.recommendation_query,
    );

    const recommendationWeights = getObject(
        recommendationQuery.field_weights,
    );

    const session = getObject(
        settings.session_recommendation,
    );

    setInputValue(
        'elastic_search_query_type',
        searchQuery.type ?? 'best_fields',
    );

    setInputValue(
        'elastic_search_operator',
        searchQuery.operator ?? 'or',
    );

    setInputValue(
        'elastic_search_name_weight',
        searchWeights.name ?? 5,
    );

    setInputValue(
        'elastic_search_category_weight',
        searchWeights.category ?? 3,
    );

    setInputValue(
        'elastic_search_description_weight',
        searchWeights.description ?? 2,
    );

    setInputValue(
        'elastic_search_material_weight',
        searchWeights.material ?? 1,
    );

    setInputValue(
        'elastic_search_color_weight',
        searchWeights.color ?? 1,
    );

    setInputValue(
        'elastic_search_size_weight',
        searchWeights.size ?? 1,
    );

    setInputValue(
        'elastic_search_sku_weight',
        searchWeights.sku ?? 2,
    );

    setInputValue(
        'elastic_recommendation_query_type',
        recommendationQuery.type ?? 'best_fields',
    );

    setInputValue(
        'elastic_recommendation_operator',
        recommendationQuery.operator ?? 'or',
    );

    setInputValue(
        'elastic_recommendation_name_weight',
        recommendationWeights.name ?? 5,
    );

    setInputValue(
        'elastic_recommendation_category_weight',
        recommendationWeights.category ?? 4,
    );

    setInputValue(
        'elastic_recommendation_description_weight',
        recommendationWeights.description ?? 2,
    );

    setInputValue(
        'elastic_recommendation_material_weight',
        recommendationWeights.material ?? 2,
    );

    setInputValue(
        'elastic_recommendation_color_weight',
        recommendationWeights.color ?? 1,
    );

    setInputValue(
        'elastic_recommendation_size_weight',
        recommendationWeights.size ?? 1,
    );

    setInputValue(
        'elastic_recommendation_sku_weight',
        recommendationWeights.sku ?? 2,
    );

    setInputValue(
        'elastic_recommendation_candidate_multiplier',
        recommendationQuery.candidate_multiplier ?? 3,
    );

    setInputValue(
        'elastic_recommendation_minimum_candidates',
        recommendationQuery.minimum_candidates ?? 20,
    );

    setCheckboxValue(
        'elastic_recommendation_exclude_source_sku',
        recommendationQuery.exclude_source_sku ?? true,
    );

    loadSessionRecommendationSettings(
        'elastic',
        session,
        2,
    );
}

function loadSessionRecommendationSettings(
    prefix,
    session,
    defaultCandidateMultiplier,
) {
    setInputValue(
        `${prefix}_session_current_product_weight`,
        session.current_product_weight ?? 1.0,
    );

    setInputValue(
        `${prefix}_session_viewed_product_weight`,
        session.viewed_product_weight ?? 0.70,
    );

    setInputValue(
        `${prefix}_session_cart_product_weight`,
        session.cart_product_weight ?? 0.90,
    );

    setInputValue(
        `${prefix}_session_max_viewed_seeds`,
        session.max_viewed_seeds ?? 5,
    );

    setInputValue(
        `${prefix}_session_max_cart_seeds`,
        session.max_cart_seeds ?? 5,
    );

    setInputValue(
        `${prefix}_session_max_total_seeds`,
        session.max_total_seeds ?? 8,
    );

    setInputValue(
        `${prefix}_session_candidate_multiplier`,
        session.candidate_multiplier
            ?? defaultCandidateMultiplier,
    );

    setInputValue(
        `${prefix}_session_minimum_candidates`,
        session.minimum_candidates ?? 10,
    );
}

function applySearchConfig(method) {
    const supportedMethods = [
        'lexical',
        'semantic_vector',
        'elasticsearch_bm25',
    ];

    if (!supportedMethods.includes(method)) {
        method = 'lexical';
    }

    const config = getObject(
        searchConfigs[method],
    );

    if (searchMethodSelect) {
        searchMethodSelect.value = method;
    }

    loadCommonSearchConfig(
        config,
        method,
    );

    if (method === 'semantic_vector') {
        loadSemanticSettings(config);
    } else if (method === 'elasticsearch_bm25') {
        loadElasticSettings(config);
    } else {
        loadLexicalSettings(config);
    }

    showSearchMethodSettings(method);

    currentSearchMethod = method;

    if (searchConfigRoot) {
        searchConfigRoot.dataset.activeMethod = method;
    }
}

function buildSessionRecommendationSettings(
    prefix,
    defaultCandidateMultiplier,
) {
    return {
        current_product_weight: readFloat(
            `${prefix}_session_current_product_weight`,
            1.0,
        ),
        viewed_product_weight: readFloat(
            `${prefix}_session_viewed_product_weight`,
            0.70,
        ),
        cart_product_weight: readFloat(
            `${prefix}_session_cart_product_weight`,
            0.90,
        ),
        max_viewed_seeds: readInteger(
            `${prefix}_session_max_viewed_seeds`,
            5,
        ),
        max_cart_seeds: readInteger(
            `${prefix}_session_max_cart_seeds`,
            5,
        ),
        max_total_seeds: readInteger(
            `${prefix}_session_max_total_seeds`,
            8,
        ),
        candidate_multiplier: readInteger(
            `${prefix}_session_candidate_multiplier`,
            defaultCandidateMultiplier,
        ),
        minimum_candidates: readInteger(
            `${prefix}_session_minimum_candidates`,
            10,
        ),
    };
}

function buildLexicalAlgorithmSettings() {
    return {
        vectorizer: {
            lowercase: readCheckbox(
                'lexical_lowercase',
                true,
            ),
            ngram_range: [
                readInteger(
                    'lexical_ngram_min',
                    1,
                ),
                readInteger(
                    'lexical_ngram_max',
                    2,
                ),
            ],
            n_features: readInteger(
                'lexical_n_features',
                262144,
            ),
            alternate_sign: readCheckbox(
                'lexical_alternate_sign',
                false,
            ),
            normalization:
                document.getElementById(
                    'lexical_normalization',
                )?.value || 'l2',
            token_pattern:
                document.getElementById(
                    'lexical_token_pattern',
                )?.value || '\\b\\w+\\b',
        },

        candidate_filter: {
            minimum_query_token_matches: readInteger(
                'lexical_candidate_minimum_query_token_matches',
                1,
            ),
            fallback_to_all_documents: readCheckbox(
                'lexical_fallback_to_all_documents',
                true,
            ),
        },

        partial_match: {
            require_all_query_tokens: readCheckbox(
                'lexical_require_all_query_tokens',
                true,
            ),
            minimum_query_token_matches: readInteger(
                'lexical_partial_minimum_query_token_matches',
                1,
            ),
            base_score: readFloat(
                'lexical_partial_base_score',
                1.0,
            ),
            merge_bonus_weight: readFloat(
                'lexical_partial_merge_bonus_weight',
                0.20,
            ),
        },

        session_recommendation:
            buildSessionRecommendationSettings(
                'lexical',
                3,
            ),
    };
}

function buildSemanticAlgorithmSettings() {
    return {
        document_fields: {
            name: readCheckbox(
                'semantic_document_name',
                true,
            ),
            category: readCheckbox(
                'semantic_document_category',
                true,
            ),
            description: readCheckbox(
                'semantic_document_description',
                true,
            ),
            material: readCheckbox(
                'semantic_document_material',
                true,
            ),
            color: readCheckbox(
                'semantic_document_color',
                true,
            ),
            size: readCheckbox(
                'semantic_document_size',
                true,
            ),
            attributes: readCheckbox(
                'semantic_document_attributes',
                false,
            ),
        },

        embedding: {
            batch_size: readInteger(
                'semantic_embedding_batch_size',
                32,
            ),
            normalize_embeddings: readCheckbox(
                'semantic_normalize_embeddings',
                true,
            ),
        },

        reranking: {
            semantic_similarity_weight: readFloat(
                'semantic_similarity_weight',
                0.75,
            ),
            lexical_overlap_weight: readFloat(
                'semantic_lexical_overlap_weight',
                0.25,
            ),
            minimum_token_length: readInteger(
                'semantic_minimum_token_length',
                2,
            ),
        },

        candidate_pool: {
            multiplier: readInteger(
                'semantic_candidate_multiplier',
                5,
            ),
            minimum_candidates: readInteger(
                'semantic_minimum_candidates',
                50,
            ),
        },

        vector_search: {
            ivfflat_probes: readInteger(
                'semantic_ivfflat_probes',
                10,
            ),
        },

        session_recommendation:
            buildSessionRecommendationSettings(
                'semantic',
                2,
            ),
    };
}

function buildElasticAlgorithmSettings() {
    return {
        search_query: {
            type:
                document.getElementById(
                    'elastic_search_query_type',
                )?.value || 'best_fields',

            operator:
                document.getElementById(
                    'elastic_search_operator',
                )?.value || 'or',

            field_weights: {
                name: readFloat(
                    'elastic_search_name_weight',
                    5,
                ),
                category: readFloat(
                    'elastic_search_category_weight',
                    3,
                ),
                description: readFloat(
                    'elastic_search_description_weight',
                    2,
                ),
                material: readFloat(
                    'elastic_search_material_weight',
                    1,
                ),
                color: readFloat(
                    'elastic_search_color_weight',
                    1,
                ),
                size: readFloat(
                    'elastic_search_size_weight',
                    1,
                ),
                sku: readFloat(
                    'elastic_search_sku_weight',
                    2,
                ),
            },
        },

        recommendation_query: {
            type:
                document.getElementById(
                    'elastic_recommendation_query_type',
                )?.value || 'best_fields',

            operator:
                document.getElementById(
                    'elastic_recommendation_operator',
                )?.value || 'or',

            field_weights: {
                name: readFloat(
                    'elastic_recommendation_name_weight',
                    5,
                ),
                category: readFloat(
                    'elastic_recommendation_category_weight',
                    4,
                ),
                description: readFloat(
                    'elastic_recommendation_description_weight',
                    2,
                ),
                material: readFloat(
                    'elastic_recommendation_material_weight',
                    2,
                ),
                color: readFloat(
                    'elastic_recommendation_color_weight',
                    1,
                ),
                size: readFloat(
                    'elastic_recommendation_size_weight',
                    1,
                ),
                sku: readFloat(
                    'elastic_recommendation_sku_weight',
                    2,
                ),
            },

            candidate_multiplier: readInteger(
                'elastic_recommendation_candidate_multiplier',
                3,
            ),

            minimum_candidates: readInteger(
                'elastic_recommendation_minimum_candidates',
                20,
            ),

            exclude_source_sku: readCheckbox(
                'elastic_recommendation_exclude_source_sku',
                true,
            ),
        },

        session_recommendation:
            buildSessionRecommendationSettings(
                'elastic',
                2,
            ),
    };
}

function buildAlgorithmSettings(method) {
    if (method === 'semantic_vector') {
        return buildSemanticAlgorithmSettings();
    }

    if (method === 'elasticsearch_bm25') {
        return buildElasticAlgorithmSettings();
    }

    return buildLexicalAlgorithmSettings();
}

function buildSearchConfigPayload(method) {
    return {
        name:
            document.getElementById(
                'search_config_name',
            )?.value
            || 'Search configuration',

        searchMethod: method,

        nameWeight: readInteger(
            'name_weight',
            20,
        ),
        descriptionWeight: readInteger(
            'description_weight',
            5,
        ),
        categoryWeight: readInteger(
            'category_weight',
            4,
        ),
        materialWeight: readInteger(
            'material_weight',
            2,
        ),
        colorWeight: readInteger(
            'color_weight',
            2,
        ),
        sizeWeight: readInteger(
            'size_weight',
            2,
        ),
        attributesWeight: readInteger(
            'attributes_weight',
            2,
        ),

        sameCategoryBonus: readFloat(
            'same_category_bonus',
            0.35,
        ),
        sameMaterialBonus: readFloat(
            'same_material_bonus',
            0.15,
        ),
        sameColorBonus: readFloat(
            'same_color_bonus',
            0.10,
        ),
        sameSizeBonus: readFloat(
            'same_size_bonus',
            0.10,
        ),

        sameCategoryRecommendationWeight: readFloat(
            'same_category_recommendation_weight',
            0.35,
        ),
        sameColorRecommendationWeight: readFloat(
            'same_color_recommendation_weight',
            0.10,
        ),
        sameSizeRecommendationWeight: readFloat(
            'same_size_recommendation_weight',
            0.10,
        ),
        wishlistRecommendationWeight: readFloat(
            'wishlist_recommendation_weight',
            0.30,
        ),
        orderHistoryRecommendationWeight: readFloat(
            'order_history_recommendation_weight',
            0.25,
        ),
        searchHistoryRecommendationWeight: readFloat(
            'search_history_recommendation_weight',
            0.20,
        ),
        viewHistoryRecommendationWeight: readFloat(
            'view_history_recommendation_weight',
            0.35,
        ),

        maxRecommendationPerCategory: readInteger(
            'max_recommendation_per_category',
            4,
        ),

        recommendationDiversityPenalty: readFloat(
            'recommendation_diversity_penalty',
            0.10,
        ),

        recommendationEnabled: readCheckbox(
            'recommendation_enabled',
            true,
        ),

        recommendationLoggingEnabled: readCheckbox(
            'recommendation_logging_enabled',
            true,
        ),

        algorithmSettings:
            buildAlgorithmSettings(method),
    };
}

function cacheCurrentSearchConfig() {
    if (!currentSearchMethod) {
        return;
    }

    searchConfigs[currentSearchMethod] =
        buildSearchConfigPayload(
            currentSearchMethod,
        );
}

function initializeSearchMethodSwitcher() {
    searchMethodSelect = document.getElementById(
        'searchMethod',
    );

    if (!searchMethodSelect) {
        console.error(
            'Search method select element was not found.',
        );
        return;
    }

    searchMethodSelect.addEventListener(
        'change',
        () => {
            const newMethod = String(
                searchMethodSelect.value || 'lexical',
            );

            if (
                currentSearchMethod
                && currentSearchMethod !== newMethod
            ) {
                cacheCurrentSearchConfig();
            }

            applySearchConfig(newMethod);
        },
    );

    const initialMethod = String(
        searchMethodSelect.value
        || currentSearchMethod
        || 'lexical',
    );

    applySearchConfig(initialMethod);
}

if (document.readyState === 'loading') {
    document.addEventListener(
        'DOMContentLoaded',
        initializeSearchMethodSwitcher,
    );
} else {
    initializeSearchMethodSwitcher();
}

/**
 * Saves e-shop settings and currency configuration to the server.
 *
 * @returns {Promise<void>}
 */
async function save_() {
    try {
        const csrfEl = document.getElementById('csrf_token_eshop_save');
        const csrfToken = csrfEl ? String(csrfEl.value || '').trim() : '';

        if (!csrfToken) {
            alert('Missing CSRF token.');
            return;
        }

        const eshopNameElement = document.getElementById('eshop_name');
        const addressElement = document.getElementById('address');
        const telElement = document.getElementById('tel');
        const emailElement = document.getElementById('email');
        const aboutElement = document.getElementById('about');
        const howToOrderElement = document.getElementById('how_to_order');
        const conditionsElement = document.getElementById('conditions');
        const privacyElement = document.getElementById('privacy');
        const shippingElement = document.getElementById('shipping');
        const paymentElement = document.getElementById('payment');
        const refundElement = document.getElementById('refund');
        const logoUrlElement = document.getElementById('logo_url');
        const companyNameElement = document.getElementById('company_name');
        const cinElement = document.getElementById('cin');
        const hidePricesElement = document.getElementById('hide_prices');
        
        const imagePaths = Array.from(
            document.querySelectorAll('.image_path'),
        )
            .map((el) => String(el.value || '').trim())
            .filter((v) => v !== '');

        const currencies = [];
        const rows = document.querySelectorAll(
            '#currency-container .currency-pair',
        );
        const codes = document.getElementsByName('currencyCode[]');
        const values = document.getElementsByName('currencyValue[]');

        const checked = document.querySelector(
            'input[name="isDefaultCurrency"]:checked',
        );
        const checkedValue = checked ? String(checked.value) : null;

        for (let i = 0; i < rows.length; i += 1) {
            const row = rows[i];

            const idAttr = row.getAttribute('data-currency-id');
            const currencyId =
                idAttr && String(idAttr).trim() !== ''
                    ? parseInt(idAttr, 10)
                    : null;

            const code = (codes[i]?.value || '').trim().toUpperCase();
            const rawVal = (values[i]?.value || '').trim();

            if (!code) {
                alert('Currency code is required.');
                return;
            }

            const value = Number(rawVal);
            if (!Number.isFinite(value) || value <= 0) {
                alert(`Invalid currency value for ${code}.`);
                return;
            }

            const isDefault = checkedValue !== null
                && (
                    (currencyId !== null
                        && checkedValue === String(currencyId))
                    || (currencyId === null
                        && checkedValue === `new-${i}`)
                );

            currencies.push({
                id: currencyId,
                name: code,
                value,
                isDefault,
            });
        }

        const selectedSearchMethod =
            searchMethodSelect?.value
            || currentSearchMethod
            || 'lexical';

        const selectedSearchConfig =
            buildSearchConfigPayload(
                selectedSearchMethod,
            );

        searchConfigs[selectedSearchMethod] =
            selectedSearchConfig;

        const requestData = {
            eshopName: eshopNameElement?.value ?? '',
            address: addressElement?.value ?? '',
            tel: telElement?.value ?? '',
            email: emailElement?.value ?? '',
            about: aboutElement?.value ?? '',
            image_urls: imagePaths,
            howToOrder: howToOrderElement?.value ?? '',
            conditions: conditionsElement?.value ?? '',
            privacy: privacyElement?.value ?? '',
            shipping: shippingElement?.value ?? '',
            payment: paymentElement?.value ?? '',
            refund: refundElement?.value ?? '',
            companyName: companyNameElement?.value ?? '',
            cin: cinElement?.value ?? '',
            hidePrices: !!hidePricesElement?.checked,
            currencies,
            searchConfig: selectedSearchConfig,
        };

        if (logoUrlElement && logoUrlElement.value) {
            requestData.logo_url = logoUrlElement.value.replace(
                'C:\\fakepath\\',
                '',
            );
        }

        const response = await fetch('/eshop_save', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            credentials: 'same-origin',
            body: JSON.stringify(requestData),
        });

        const responseText = await response.text();

        if (!response.ok) {
            alert(responseText || 'Failed to save shop settings.');
            return;
        }

        alert('Shop settings saved successfully.');
    } catch (error) {
        console.error(error);
        alert('An unexpected error occurred. Please try again.');
    }
}

/**
 * Adds a new currency input pair.
 */
function addCurrency() {
    const container = document.getElementById('currency-container');
    const index = container.querySelectorAll('.currency-pair').length;

    const currencyPair = document.createElement('div');
    currencyPair.className = 'currency-pair';
    currencyPair.setAttribute('data-currency-id', '');

    currencyPair.innerHTML = `
        <input type="text" name="currencyCode[]" placeholder="Currency Code (e.g., USD)" required>
        <input type="number" name="currencyValue[]" placeholder="Value" step="0.01" required>
        <input type="radio" name="isDefaultCurrency" value="new-${index}"> Default
        <button type="button" class="delete-currency" onclick="deleteCurrency(this)">Delete</button>
    `;

    container.appendChild(currencyPair);
}

/**
 * Deletes a currency input pair.
 *
 * @param {HTMLButtonElement} button - Delete button element.
 */
function deleteCurrency(button) {
    const currencyPair = button.parentElement;
    currencyPair.remove();
}