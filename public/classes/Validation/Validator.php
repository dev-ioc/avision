<?php
/**
 * Classe de validation centralisée
 * 
 * Fournit des méthodes de validation réutilisables pour les données d'entrée
 */
class Validator {
    /**
     * Erreurs de validation
     * @var array
     */
    private $errors = [];
    
    /**
     * Données validées
     * @var array
     */
    private $validated = [];
    
    /**
     * Constructeur
     * 
     * @param array $data Les données à valider
     */
    public function __construct(array $data = []) {
        $this->data = $data;
    }
    
    /**
     * Valide un champ selon des règles
     * 
     * @param string $field Le nom du champ
     * @param array|string $rules Les règles de validation (peut être un string séparé par |)
     * @param string|null $label Le label du champ (pour les messages d'erreur)
     * @return self
     */
    public function validate(string $field, $rules, ?string $label = null): self {
        $value = $this->data[$field] ?? null;
        $label = $label ?? $field;
        
        // Si les règles sont une string, les convertir en tableau
        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }
        
        foreach ($rules as $rule) {
            $ruleParts = explode(':', $rule, 2);
            $ruleName = $ruleParts[0];
            $ruleValue = $ruleParts[1] ?? null;
            
            // Vérifier si le champ est requis
            if ($ruleName === 'required') {
                if (empty($value) && $value !== '0' && $value !== 0) {
                    $this->addError($field, "$label est obligatoire.");
                    return $this; // Arrêter la validation si requis et vide
                }
            }
            
            // Si le champ n'est pas requis et est vide, passer les autres validations
            if (empty($value) && $value !== '0' && $value !== 0) {
                continue;
            }
            
            // Appliquer les règles de validation
            switch ($ruleName) {
                case 'email':
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $this->addError($field, "$label doit être une adresse email valide.");
                    }
                    break;
                    
                case 'integer':
                case 'int':
                    if (!is_numeric($value) || (int)$value != $value) {
                        $this->addError($field, "$label doit être un nombre entier.");
                    } else {
                        $this->validated[$field] = (int)$value;
                    }
                    break;
                    
                case 'numeric':
                case 'number':
                    if (!is_numeric($value)) {
                        $this->addError($field, "$label doit être un nombre.");
                    } else {
                        $this->validated[$field] = is_int($value) ? (int)$value : (float)$value;
                    }
                    break;
                    
                case 'min':
                    $min = (int)$ruleValue;
                    if (is_numeric($value) && (float)$value < $min) {
                        $this->addError($field, "$label doit être au moins $min.");
                    } elseif (is_string($value) && strlen($value) < $min) {
                        $this->addError($field, "$label doit contenir au moins $min caractères.");
                    }
                    break;
                    
                case 'max':
                    $max = (int)$ruleValue;
                    if (is_numeric($value) && (float)$value > $max) {
                        $this->addError($field, "$label ne doit pas dépasser $max.");
                    } elseif (is_string($value) && strlen($value) > $max) {
                        $this->addError($field, "$label ne doit pas dépasser $max caractères.");
                    }
                    break;
                    
                case 'length':
                    $length = (int)$ruleValue;
                    if (strlen($value) !== $length) {
                        $this->addError($field, "$label doit contenir exactement $length caractères.");
                    }
                    break;
                    
                case 'date':
                    $date = DateTime::createFromFormat('Y-m-d', $value);
                    if (!$date || $date->format('Y-m-d') !== $value) {
                        $this->addError($field, "$label doit être une date valide (format: YYYY-MM-DD).");
                    }
                    break;
                    
                case 'datetime':
                    $datetime = DateTime::createFromFormat('Y-m-d H:i:s', $value);
                    if (!$datetime || $datetime->format('Y-m-d H:i:s') !== $value) {
                        $this->addError($field, "$label doit être une date et heure valides (format: YYYY-MM-DD HH:MM:SS).");
                    }
                    break;
                    
                case 'in':
                    $allowed = explode(',', $ruleValue);
                    if (!in_array($value, $allowed)) {
                        $this->addError($field, "$label doit être l'une des valeurs suivantes: " . implode(', ', $allowed) . ".");
                    }
                    break;
                    
                case 'regex':
                    if (!preg_match($ruleValue, $value)) {
                        $this->addError($field, "$label n'est pas au format attendu.");
                    }
                    break;
                    
                case 'url':
                    if (!filter_var($value, FILTER_VALIDATE_URL)) {
                        $this->addError($field, "$label doit être une URL valide.");
                    }
                    break;
                    
                case 'boolean':
                case 'bool':
                    if (!in_array($value, [true, false, 1, 0, '1', '0', 'true', 'false'], true)) {
                        $this->addError($field, "$label doit être un booléen.");
                    } else {
                        $this->validated[$field] = in_array($value, [true, 1, '1', 'true'], true);
                    }
                    break;
            }
        }
        
        // Si pas d'erreur et pas encore dans validated, ajouter la valeur
        if (!isset($this->errors[$field]) && !isset($this->validated[$field])) {
            $this->validated[$field] = $value;
        }
        
        return $this;
    }
    
    /**
     * Ajoute une erreur pour un champ
     * 
     * @param string $field Le nom du champ
     * @param string $message Le message d'erreur
     */
    private function addError(string $field, string $message): void {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }
    
    /**
     * Vérifie si la validation a échoué
     * 
     * @return bool True si il y a des erreurs
     */
    public function fails(): bool {
        return !empty($this->errors);
    }
    
    /**
     * Vérifie si la validation a réussi
     * 
     * @return bool True si il n'y a pas d'erreurs
     */
    public function passes(): bool {
        return empty($this->errors);
    }
    
    /**
     * Récupère toutes les erreurs
     * 
     * @return array Tableau des erreurs [field => [messages]]
     */
    public function errors(): array {
        return $this->errors;
    }
    
    /**
     * Récupère toutes les erreurs comme un tableau plat
     * 
     * @return array Tableau plat des messages d'erreur
     */
    public function errorsFlat(): array {
        $flat = [];
        foreach ($this->errors as $fieldErrors) {
            $flat = array_merge($flat, $fieldErrors);
        }
        return $flat;
    }
    
    /**
     * Récupère toutes les erreurs comme une chaîne (pour affichage)
     * 
     * @param string $separator Séparateur entre les erreurs
     * @return string Chaîne des erreurs
     */
    public function errorsString(string $separator = '<br>'): string {
        return implode($separator, $this->errorsFlat());
    }
    
    /**
     * Récupère les données validées
     * 
     * @return array Les données validées
     */
    public function validated(): array {
        return $this->validated;
    }
    
    /**
     * Récupère une valeur validée
     * 
     * @param string $field Le nom du champ
     * @param mixed $default La valeur par défaut
     * @return mixed La valeur validée ou la valeur par défaut
     */
    public function get(string $field, $default = null) {
        return $this->validated[$field] ?? $default;
    }
    
    /**
     * Méthode statique pour valider rapidement
     * 
     * @param array $data Les données à valider
     * @param array $rules Les règles de validation ['field' => 'required|email', ...]
     * @return Validator L'instance du validateur
     */
    public static function make(array $data, array $rules): Validator {
        $validator = new self($data);
        
        foreach ($rules as $field => $fieldRules) {
            $validator->validate($field, $fieldRules);
        }
        
        return $validator;
    }
}
