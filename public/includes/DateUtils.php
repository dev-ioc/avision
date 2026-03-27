<?php
/**
 * Utilitaires pour la gestion des dates
 */
class DateUtils {
    
    /**
     * Liste des jours fériés français
     */
    private static $holidays = [
        '01-01', // Nouvel An
        '05-01', // Fête du Travail
        '05-08', // Victoire 1945
        '07-14', // Fête Nationale
        '08-15', // Assomption
        '11-01', // Toussaint
        '11-11', // Armistice
        '12-25', // Noël
    ];
    
    /**
     * Ajuste une date pour éviter les weekends et jours fériés
     * @param DateTime $date Date à ajuster
     * @return DateTime Date ajustée
     */
    public static function adjustForWeekendsAndHolidays($date) {
        $originalDate = clone $date;
        $maxAttempts = 10; // Éviter les boucles infinies
        $attempts = 0;
        
        while ($attempts < $maxAttempts) {
            $dayOfWeek = $date->format('N'); // 1 (Lundi) à 7 (Dimanche)
            $monthDay = $date->format('m-d');
            
            // Vérifier si c'est un weekend (6=Samedi, 7=Dimanche)
            if ($dayOfWeek >= 6) {
                $date->add(new DateInterval('P1D'));
                $attempts++;
                continue;
            }
            
            // Vérifier si c'est un jour férié
            if (in_array($monthDay, self::$holidays)) {
                $date->add(new DateInterval('P1D'));
                $attempts++;
                continue;
            }
            
            // Date valide trouvée
            break;
        }
        
        return $date;
    }
    
    /**
     * Vérifie si une date est un jour ouvré (pas weekend ni férié)
     * @param DateTime $date Date à vérifier
     * @return bool True si jour ouvré
     */
    public static function isWorkingDay($date) {
        $dayOfWeek = $date->format('N'); // 1 (Lundi) à 7 (Dimanche)
        $monthDay = $date->format('m-d');
        
        // Vérifier si c'est un weekend
        if ($dayOfWeek >= 6) {
            return false;
        }
        
        // Vérifier si c'est un jour férié
        if (in_array($monthDay, self::$holidays)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Programme automatiquement les interventions préventives pour un contrat
     * @param int $nbInterventions Nombre d'interventions à programmer
     * @param string $startDate Date de début du contrat
     * @param string $endDate Date de fin du contrat
     * @param string $defaultHour Heure par défaut (format HH:MM)
     * @param array $room Informations de la salle (optionnel)
     * @return array Dates proposées pour les interventions
     */
    public static function schedulePreventiveInterventions($nbInterventions, $startDate, $endDate, $defaultHour = '09:00', $room = null) {
        $dates = [];
        
        if ($nbInterventions <= 0) {
            return $dates;
        }
        
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        $interval = $end->diff($start);
        $totalDays = $interval->days;
        
        // Calculer l'intervalle entre chaque intervention
        // On ajoute 1 pour éviter de programmer le premier jour
        $intervalBetweenInterventions = $totalDays / ($nbInterventions + 1);
        
        for ($i = 1; $i <= $nbInterventions; $i++) {
            $targetDate = clone $start;
            $targetDate->add(new DateInterval('P' . round($intervalBetweenInterventions * $i) . 'D'));
            
            // Ajuster pour éviter weekends et jours fériés
            $adjustedDate = self::adjustForWeekendsAndHolidays($targetDate);
            
            $dates[] = [
                'date' => $adjustedDate->format('Y-m-d'),
                'heure' => $defaultHour,
                'title' => "Intervention préventive {$i}/{$nbInterventions}",
                'description' => "Intervention préventive programmée automatiquement lors de la création du contrat"
            ];
        }
        
        return $dates;
    }
    
    /**
     * Récupère la liste des jours fériés
     * @return array Liste des jours fériés
     */
    public static function getHolidays() {
        return self::$holidays;
    }
    
    /**
     * Ajoute un jour férié à la liste
     * @param string $date Date au format MM-DD
     */
    public static function addHoliday($date) {
        if (!in_array($date, self::$holidays)) {
            self::$holidays[] = $date;
        }
    }
    
    /**
     * Supprime un jour férié de la liste
     * @param string $date Date au format MM-DD
     */
    public static function removeHoliday($date) {
        $key = array_search($date, self::$holidays);
        if ($key !== false) {
            unset(self::$holidays[$key]);
            self::$holidays = array_values(self::$holidays);
        }
    }
} 