/**
 * ApiClient - Classe pour centraliser toutes les requêtes AJAX
 * Remplace les 277+ occurrences de fetch() inline dans les vues
 */

'use strict';

class ApiClient {
    /**
     * Effectue une requête GET
     * @param {string} url - URL relative (sera préfixée par BASE_URL)
     * @param {Object} options - Options supplémentaires
     * @returns {Promise} Promise résolue avec les données JSON
     */
    static async get(url, options = {}) {
        return this.request('GET', url, null, options);
    }
    
    /**
     * Effectue une requête POST
     * @param {string} url - URL relative (sera préfixée par BASE_URL)
     * @param {Object|FormData} data - Données à envoyer
     * @param {Object} options - Options supplémentaires
     * @returns {Promise} Promise résolue avec les données JSON
     */
    static async post(url, data = null, options = {}) {
        return this.request('POST', url, data, options);
    }
    
    /**
     * Effectue une requête PUT
     * @param {string} url - URL relative (sera préfixée par BASE_URL)
     * @param {Object|FormData} data - Données à envoyer
     * @param {Object} options - Options supplémentaires
     * @returns {Promise} Promise résolue avec les données JSON
     */
    static async put(url, data = null, options = {}) {
        return this.request('PUT', url, data, options);
    }
    
    /**
     * Effectue une requête DELETE
     * @param {string} url - URL relative (sera préfixée par BASE_URL)
     * @param {Object} options - Options supplémentaires
     * @returns {Promise} Promise résolue avec les données JSON
     */
    static async delete(url, options = {}) {
        return this.request('DELETE', url, null, options);
    }
    
    /**
     * Effectue une requête PATCH
     * @param {string} url - URL relative (sera préfixée par BASE_URL)
     * @param {Object|FormData} data - Données à envoyer
     * @param {Object} options - Options supplémentaires
     * @returns {Promise} Promise résolue avec les données JSON
     */
    static async patch(url, data = null, options = {}) {
        return this.request('PATCH', url, data, options);
    }
    
    /**
     * Méthode principale pour effectuer une requête
     * @param {string} method - Méthode HTTP (GET, POST, PUT, DELETE, PATCH)
     * @param {string} url - URL relative (sera préfixée par BASE_URL)
     * @param {Object|FormData|null} data - Données à envoyer (null pour GET/DELETE)
     * @param {Object} options - Options supplémentaires
     * @returns {Promise} Promise résolue avec les données JSON
     */
    static async request(method, url, data = null, options = {}) {
        const config = window.AppConfig || {};
        const baseUrl = config.baseUrl || window.BASE_URL || '';
        const csrfToken = config.csrfToken || window.CSRF_TOKEN || '';
        
        // Construire l'URL complète
        const fullUrl = url.startsWith('http') ? url : baseUrl + url;
        
        // Configuration par défaut
        const defaultHeaders = {
            'X-Requested-With': 'XMLHttpRequest'
        };
        
        // Ajouter le token CSRF pour les méthodes modifiantes
        if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method.toUpperCase()) && csrfToken) {
            defaultHeaders['X-CSRF-Token'] = csrfToken;
        }
        
        // Préparer les headers
        let headers = { ...defaultHeaders };
        
        // Si data est FormData, ne pas définir Content-Type (le navigateur le fera)
        // Sinon, utiliser application/json
        if (data && !(data instanceof FormData)) {
            headers['Content-Type'] = 'application/json';
        }
        
        // Fusionner avec les headers personnalisés
        const { headers: optionHeaders, ...requestOptions } = options || {};
        if (optionHeaders) {
            headers = { ...headers, ...optionHeaders };
        }
        
        // Configuration de la requête
        const requestConfig = {
            method: method.toUpperCase(),
            credentials: 'same-origin', // Inclure les cookies
            ...requestOptions, // Permettre de surcharger d'autres options (timeout, etc.)
            // IMPORTANT: headers DOIT être appliqué après le spread des options,
            // sinon options.headers (même {}) écrase X-Requested-With / X-CSRF-Token.
            headers: headers
        };
        
        // Ajouter le body si nécessaire
        if (data && method.toUpperCase() !== 'GET') {
            if (data instanceof FormData) {
                requestConfig.body = data;
            } else {
                requestConfig.body = JSON.stringify(data);
            }
        }
        
        // Effectuer la requête avec retry
        const maxRetries = options.retryAttempts || config.ajax?.retryAttempts || 0;
        let lastError = null;
        
        for (let attempt = 0; attempt <= maxRetries; attempt++) {
            try {
                const response = await fetch(fullUrl, requestConfig);
                const contentType = (response.headers.get('content-type') || '').toLowerCase();
                const isJson = contentType.includes('application/json') || contentType.includes('+json');
                
                // Gérer les erreurs HTTP
                if (!response.ok) {
                    // Essayer de parser le JSON d'erreur
                    let errorData;
                    if (isJson) {
                        try {
                            errorData = await response.json();
                        } catch (e) {
                            errorData = { error: `Erreur HTTP ${response.status}: ${response.statusText}` };
                        }
                    } else {
                        const text = await response.text().catch(() => '');
                        errorData = {
                            error: `Réponse non-JSON (${response.status}). Probable page HTML (login/erreur).`,
                            url: response.url,
                            redirected: response.redirected,
                            contentType,
                            textSnippet: text.slice(0, 300)
                        };
                    }
                    
                    // Si c'est une erreur serveur (5xx) et qu'on peut retry, on retry
                    if (response.status >= 500 && attempt < maxRetries) {
                        await this._delay(options.retryDelay || config.ajax?.retryDelay || 1000);
                        continue;
                    }
                    
                    throw new ApiError(
                        errorData.error || errorData.message || `Erreur HTTP ${response.status}`,
                        response.status,
                        errorData
                    );
                }
                
                // Succès HTTP mais réponse non-JSON -> cas typique: redirection suivie (login) ou HTML d'erreur
                if (!isJson) {
                    const text = await response.text().catch(() => '');
                    throw new ApiError(
                        'Réponse non-JSON (200). Le serveur a renvoyé du HTML au lieu de JSON.',
                        response.status,
                        {
                            url: response.url,
                            redirected: response.redirected,
                            contentType,
                            textSnippet: text.slice(0, 300)
                        }
                    );
                }

                // Parser la réponse JSON
                const result = await response.json();
                
                // Vérifier si la réponse contient une erreur
                if (result.error) {
                    throw new ApiError(result.error, response.status, result);
                }
                
                return result;
                
            } catch (error) {
                lastError = error;
                
                // Si c'est une erreur réseau et qu'on peut retry, on retry
                if (error instanceof TypeError && attempt < maxRetries) {
                    await this._delay(options.retryDelay || config.ajax?.retryDelay || 1000);
                    continue;
                }
                
                // Sinon, on propage l'erreur
                throw error;
            }
        }
        
        // Si on arrive ici, tous les retries ont échoué
        throw lastError || new ApiError('Erreur inconnue lors de la requête');
    }
    
    /**
     * Délai pour les retries
     * @private
     */
    static _delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
}

/**
 * Classe d'erreur personnalisée pour les erreurs API
 */
class ApiError extends Error {
    constructor(message, status = null, data = null) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.data = data;
    }
}

// Exporter pour utilisation globale
if (typeof window !== 'undefined') {
    window.ApiClient = ApiClient;
    window.ApiError = ApiError;
}

// Exporter pour modules ES6 (si utilisé plus tard)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { ApiClient, ApiError };
}
