<?php
/**
 * Règles de validation prédéfinies
 * 
 * Contient des règles de validation réutilisables pour les entités courantes
 */
class ValidationRules {
    /**
     * Règles pour la création d'un client
     */
    public static function client(): array {
        return [
            'name' => 'required|min:2|max:255',
            'address' => 'max:500',
            'city' => 'max:100',
            'postal_code' => 'max:10',
            'phone' => 'max:20',
            'email' => 'email|max:255',
            'siret' => 'max:14',
            'vat_number' => 'max:20'
        ];
    }
    
    /**
     * Règles pour la création d'une intervention
     */
    public static function intervention(): array {
        return [
            'title' => 'required|min:3|max:255',
            'client_id' => 'required|integer|min:1',
            'site_id' => 'integer|min:1',
            'room_id' => 'integer|min:1',
            'technician_id' => 'integer|min:1',
            'status_id' => 'required|integer|min:1',
            'priority_id' => 'required|integer|min:1',
            'type_id' => 'required|integer|min:1',
            'duration' => 'numeric|min:0',
            'description' => 'max:5000',
            'contact_client' => 'email|max:255',
            'date_planif' => 'date',
            'heure_planif' => 'regex:/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/'
        ];
    }
    
    /**
     * Règles pour la création d'un contrat
     */
    public static function contract(): array {
        return [
            'client_id' => 'required|integer|min:1',
            'contract_type_id' => 'required|integer|min:1',
            'access_level_id' => 'required|integer|min:1',
            'name' => 'required|min:3|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'tickets_number' => 'integer|min:0',
            'comment' => 'max:2000',
            'reminder_days' => 'integer|min:0|max:365',
            'tarif' => 'numeric|min:0',
            'isticketcontract' => 'boolean'
        ];
    }
    
    /**
     * Règles pour la création d'un site
     */
    public static function site(): array {
        return [
            'name' => 'required|min:2|max:255',
            'client_id' => 'required|integer|min:1',
            'address' => 'max:500',
            'city' => 'max:100',
            'postal_code' => 'max:10',
            'main_contact_id' => 'integer|min:1'
        ];
    }
    
    /**
     * Règles pour la création d'une salle
     */
    public static function room(): array {
        return [
            'name' => 'required|min:2|max:255',
            'site_id' => 'required|integer|min:1',
            'main_contact_id' => 'integer|min:1'
        ];
    }
    
    /**
     * Règles pour la création d'un contact
     */
    public static function contact(): array {
        return [
            'first_name' => 'required|min:2|max:100',
            'last_name' => 'required|min:2|max:100',
            'email' => 'email|max:255',
            'phone' => 'max:20',
            'mobile' => 'max:20',
            'function' => 'max:100',
            'client_id' => 'required|integer|min:1'
        ];
    }
    
    /**
     * Règles pour la création d'un utilisateur
     */
    public static function user(): array {
        return [
            'username' => 'required|min:3|max:50|regex:/^[a-zA-Z0-9_]+$/',
            'email' => 'required|email|max:255',
            'first_name' => 'required|min:2|max:100',
            'last_name' => 'required|min:2|max:100',
            'phone' => 'max:20',
            'user_type_id' => 'required|integer|min:1',
            'group_id' => 'required|integer|min:1'
        ];
    }
    
    /**
     * Règles pour la validation d'un ID
     */
    public static function id(): string {
        return 'required|integer|min:1';
    }
    
    /**
     * Règles pour la validation d'un email
     */
    public static function email(): string {
        return 'required|email|max:255';
    }
    
    /**
     * Règles pour la validation d'un email optionnel
     */
    public static function emailOptional(): string {
        return 'email|max:255';
    }
}
