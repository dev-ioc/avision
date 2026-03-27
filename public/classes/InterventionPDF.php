<?php
/**
 * Classe pour la génération du bon d'intervention en PDF
 */
class InterventionPDF extends TCPDF {
    // Constantes pour la configuration du PDF
    const PDF_PAGE_ORIENTATION = 'P'; // P = Portrait, L = Landscape
    const PDF_UNIT = 'mm';
    const PDF_PAGE_FORMAT = 'A4';
    const PDF_CREATOR = 'VideoSonic Support';
    const PDF_MARGIN_LEFT = 15;
    const PDF_MARGIN_TOP = 15;
    const PDF_MARGIN_RIGHT = 15;
    const PDF_MARGIN_BOTTOM = 15;
    const PDF_FONT_NAME_MAIN = 'helvetica';
    const PDF_FONT_SIZE_MAIN = 9;
    const PDF_FONT_NAME_DATA = 'helvetica';
    const PDF_FONT_SIZE_DATA = 8;
    const PDF_FONT_MONOSPACED = 'courier';
    const PDF_IMAGE_SCALE_RATIO = 1.25;
    const HEAD_MAGNIFICATION = 1.1;
    const K_CELL_HEIGHT_RATIO = 1.25;
    const K_TITLE_MAGNIFICATION = 1.3;
    const K_SMALL_RATIO = 2/3;

    // Couleurs
    private $primaryColor = array(0, 123, 255); // Bleu
    private $secondaryColor = array(108, 117, 125); // Gris
    private $borderColor = array(200, 200, 200); // Gris clair

    /**
     * Constructeur
     */
    public function __construct() {
        parent::__construct(
            self::PDF_PAGE_ORIENTATION,
            self::PDF_UNIT,
            self::PDF_PAGE_FORMAT,
            true,
            'UTF-8',
            false
        );

        // Configuration de base
        $this->SetCreator(self::PDF_CREATOR);
        $this->SetAuthor('VideoSonic Support');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetMargins(
            self::PDF_MARGIN_LEFT,
            self::PDF_MARGIN_TOP,
            self::PDF_MARGIN_RIGHT
        );
        $this->SetAutoPageBreak(true, self::PDF_MARGIN_BOTTOM);
        $this->SetFont(self::PDF_FONT_NAME_MAIN, '', self::PDF_FONT_SIZE_MAIN);
    }

    /**
     * Génère le bon d'intervention
     * @param array $intervention Les données de l'intervention
     * @param array $solutions Les solutions apportées
     * @return string Le chemin du fichier PDF généré
     */
    public function generate($intervention, $solutions) {
        // Ajouter une page
        $this->AddPage();

        // En-tête
        $this->addHeader();

        // Titre du document
        $this->addTitle($intervention);

        // Informations de l'intervention
        $this->addInterventionInfo($intervention);

        // Description
        $this->addDescription($intervention);

        // Solutions
        if (!empty($solutions)) {
            $this->addSolutions($solutions);
        }

        // Signatures
        $this->addSignatures();

        // Pied de page
        $this->addFooter();

        return $this;
    }

    /**
     * Génère le bon d'intervention avec les éléments sélectionnés
     * @param array $intervention Les données de l'intervention
     * @param array $comments Les commentaires sélectionnés
     * @param array $attachments Les pièces jointes sélectionnées
     * @return string Le chemin du fichier PDF généré
     */
    public function generateBonIntervention($intervention, $comments, $attachments) {
        
        // Ajouter une page
        $this->AddPage();

        // En-tête
        $this->addHeader();

        // Titre du document
        $this->addTitle($intervention);

        // Informations de l'intervention
        $this->addInterventionInfo($intervention);

        // Déclarant et intervenant
        $this->addDeclarantIntervenant($intervention);

        // Informations contrat et client
        $this->addContractClientInfo($intervention);

        // Durée et estimations tickets
        $this->addDurationAndTicketsInfo($intervention);

        // Description
        $this->addDescription($intervention);

        // Solutions et observations sélectionnées
        if (!empty($comments)) {
            $this->addSolutionsAndObservations($comments);
        }

        // Images sélectionnées
        if (!empty($attachments)) {
            $this->addSelectedImages($attachments);
        }

        // Signatures
        $this->addSignatures();

        // Pied de page
        $this->addFooter();

        return $this;
    }

    /**
     * Ajoute l'en-tête du document
     */
    private function addHeader() {
        // Calculer les positions pour centrer les logos
        $pageWidth = $this->GetPageWidth();
        $logoWidth = 30; // Taille des logos
        $spacing = 40; // Espacement entre les logos (2cm)
        $totalWidth = (2 * $logoWidth) + $spacing;
        $startX = ($pageWidth - $totalWidth) / 2; // Position de départ pour centrer
        
        // Logo VideoSonic
        $logoVSPath = __DIR__ . '/../assets/img/logo_vs.png';
        if (file_exists($logoVSPath)) {
            $this->Image($logoVSPath, $startX, 10, $logoWidth); // Logo VideoSonic à gauche
        }
        
        // Logo AVision
        $logoAVisionPath = __DIR__ . '/../assets/img/logo_avision.png';
        if (file_exists($logoAVisionPath)) {
            $this->Image($logoAVisionPath, $startX + $logoWidth + $spacing, 10, $logoWidth); // Logo AVision à droite
        }
    }

    /**
     * Ajoute le titre du document
     */
    private function addTitle($intervention) {
        $this->Ln(15); // Réduit l'espacement après les logos
        $this->SetFont(self::PDF_FONT_NAME_MAIN, '', 12); // Police plus petite
        $this->Cell(0, 6, 'Bon d\'intervention', 0, 1, 'C');
        $this->Ln(3); // Réduit l'espacement après le titre
    }

    /**
     * Ajoute les informations de l'intervention
     */
    private function addInterventionInfo($intervention) {
        $this->SetFillColor($this->borderColor[0], $this->borderColor[1], $this->borderColor[2]);
        $this->SetDrawColor($this->borderColor[0], $this->borderColor[1], $this->borderColor[2]);
        
        // Calculer la largeur disponible en tenant compte des marges
        $pageWidth = $this->GetPageWidth();
        $availableWidth = $pageWidth - self::PDF_MARGIN_LEFT - self::PDF_MARGIN_RIGHT;
        $columnWidth = $availableWidth / 2; // Diviser la largeur disponible en deux colonnes
        
        // Créer le tableau compact sur 2 colonnes
        $this->SetFont(self::PDF_FONT_NAME_MAIN, 'B', 7); // Police plus petite
        
        // Première ligne
        $this->Cell($columnWidth, 5, 'Référence ticket interne', 1, 0, 'L', true);
        $this->Cell($columnWidth, 5, 'Date et heure de création ticket', 1, 1, 'L', true);
        $this->SetFont(self::PDF_FONT_NAME_MAIN, '', 7); // Police plus petite
        $this->Cell($columnWidth, 5, $intervention['reference'], 1, 0, 'L');
        $this->Cell($columnWidth, 5, date('d/m/Y H:i', strtotime($intervention['created_at'])), 1, 1, 'L');
        
        // Deuxième ligne
        $this->SetFont(self::PDF_FONT_NAME_MAIN, 'B', 7); // Police plus petite
        $this->Cell($columnWidth, 5, 'Référence client', 1, 0, 'L', true);
        $this->Cell($columnWidth, 5, 'Date et heure prévisionnelle de l\'intervention', 1, 1, 'L', true);
        $this->SetFont(self::PDF_FONT_NAME_MAIN, '', 7); // Police plus petite
        $this->Cell($columnWidth, 5, $intervention['ref_client'] ?? 'Non spécifiée', 1, 0, 'L');
        // Construire la date prévisionnelle à partir de date_planif et heure_planif
        $plannedDateTime = 'Non spécifiée';
        if (!empty($intervention['date_planif'])) {
            $dateStr = $intervention['date_planif'];
            $timeStr = !empty($intervention['heure_planif']) ? $intervention['heure_planif'] : '00:00:00';
            $plannedDateTime = date('d/m/Y H:i', strtotime($dateStr . ' ' . $timeStr));
        }
        $this->Cell($columnWidth, 5, $plannedDateTime, 1, 1, 'L');
        
        $this->Ln(3); // Réduit l'espace après le tableau
    }

    /**
     * Ajoute les informations du déclarant et de l'intervenant
     */
    private function addDeclarantIntervenant($intervention) {
        $this->SetFillColor($this->borderColor[0], $this->borderColor[1], $this->borderColor[2]);
        $this->SetDrawColor($this->borderColor[0], $this->borderColor[1], $this->borderColor[2]);
        
        // Calculer la largeur disponible
        $pageWidth = $this->GetPageWidth();
        $availableWidth = $pageWidth - self::PDF_MARGIN_LEFT - self::PDF_MARGIN_RIGHT;
        $columnWidth = $availableWidth / 2;
        
        // Créer le tableau sur 1 ligne et 2 colonnes
        $this->SetFont(self::PDF_FONT_NAME_MAIN, 'B', 7); // Police plus petite
        
        // Ligne unique
        $this->Cell($columnWidth, 5, 'Nom du déclarant', 1, 0, 'L', true);
        $this->Cell($columnWidth, 5, 'Nom de l\'intervenant', 1, 1, 'L', true);
        $this->SetFont(self::PDF_FONT_NAME_MAIN, '', 7); // Police plus petite
        
        // Déterminer le nom du déclarant
        $declarantName = !empty($intervention['demande_par']) ? $intervention['demande_par'] : 'Non renseigné';
        
        // Nom de l'intervenant
        $intervenantName = $intervention['technician_first_name'] . ' ' . $intervention['technician_last_name'];
        
        $this->Cell($columnWidth, 5, $declarantName, 1, 0, 'L');
        $this->Cell($columnWidth, 5, $intervenantName, 1, 1, 'L');
        
        $this->Ln(3); // Réduit l'espace après le tableau
    }

    /**
     * Ajoute les informations du contrat et du client
     */
    private function addContractClientInfo($intervention) {
        
        $this->SetFillColor($this->borderColor[0], $this->borderColor[1], $this->borderColor[2]);
        $this->SetDrawColor($this->borderColor[0], $this->borderColor[1], $this->borderColor[2]);
        
        // Calculer la largeur disponible
        $pageWidth = $this->GetPageWidth();
        $availableWidth = $pageWidth - self::PDF_MARGIN_LEFT - self::PDF_MARGIN_RIGHT;
        $columnWidth = $availableWidth / 2;
        
        // Créer le tableau sur 4 lignes et 2 colonnes
        $this->SetFont(self::PDF_FONT_NAME_MAIN, 'B', 7); // Police plus petite
        
        // Ligne 1
        $this->Cell($columnWidth, 5, 'Numéro de contrat', 1, 0, 'L', true);
        $this->Cell($columnWidth, 5, 'Type de contrat', 1, 1, 'L', true);
        $this->SetFont(self::PDF_FONT_NAME_MAIN, '', 7); // Police plus petite
        $this->Cell($columnWidth, 5, $intervention['contract_name'] ?? 'Non spécifié', 1, 0, 'L');
        // Type de contrat
        $contractType = 'Non spécifié';
        if (!empty($intervention['contract_type_name'])) {
            $contractType = $intervention['contract_type_name'];
        } elseif (!empty($intervention['contract_type_id'])) {
            // Si on a l'ID mais pas le nom, c'est que la jointure a échoué
            $contractType = 'Type ID: ' . $intervention['contract_type_id'];
        }
        $this->Cell($columnWidth, 5, $contractType, 1, 1, 'L');
        
        // Ligne 2
        $this->SetFont(self::PDF_FONT_NAME_MAIN, 'B', 7); // Police plus petite
        $this->Cell($columnWidth, 5, 'Nom du client', 1, 0, 'L', true);
        $this->Cell($columnWidth, 5, 'Nom du contact', 1, 1, 'L', true);
        $this->SetFont(self::PDF_FONT_NAME_MAIN, '', 7); // Police plus petite
        $this->Cell($columnWidth, 5, $intervention['client_name'] ?? 'Non spécifié', 1, 0, 'L');
        $contactName = '';
        if (!empty($intervention['contact_first_name']) && !empty($intervention['contact_last_name'])) {
            $contactName = $intervention['contact_first_name'] . ' ' . $intervention['contact_last_name'];
        }
        $this->Cell($columnWidth, 5, $contactName ?: 'Non spécifié', 1, 1, 'L');
        
        // Ligne 3
        $this->SetFont(self::PDF_FONT_NAME_MAIN, 'B', 7); // Police plus petite
        $this->Cell($columnWidth, 5, 'Adresse', 1, 0, 'L', true);
        $this->Cell($columnWidth, 5, 'Nom du site', 1, 1, 'L', true);
        $this->SetFont(self::PDF_FONT_NAME_MAIN, '', 7); // Police plus petite
        
        // Construire l'adresse complète
        $addressParts = [];
        if (!empty($intervention['site_address'])) {
            $addressParts[] = $intervention['site_address'];
        }
        if (!empty($intervention['site_postal_code'])) {
            $addressParts[] = $intervention['site_postal_code'];
        }
        if (!empty($intervention['site_city'])) {
            $addressParts[] = $intervention['site_city'];
        }
        $fullAddress = !empty($addressParts) ? implode(', ', $addressParts) : 'Non spécifiée';
        
        $this->Cell($columnWidth, 5, $fullAddress, 1, 0, 'L');
        $this->Cell($columnWidth, 5, $intervention['site_name'] ?? 'Non spécifié', 1, 1, 'L');
        
        // Ligne 4
        $this->SetFont(self::PDF_FONT_NAME_MAIN, 'B', 7); // Police plus petite
        $this->Cell($columnWidth, 5, 'Nom de la salle', 1, 0, 'L', true);
        $this->Cell($columnWidth, 5, 'Numéro du contact', 1, 1, 'L', true);
        $this->SetFont(self::PDF_FONT_NAME_MAIN, '', 7); // Police plus petite
        $this->Cell($columnWidth, 5, $intervention['room_name'] ?? 'Non spécifiée', 1, 0, 'L');
        $this->Cell($columnWidth, 5, $intervention['contact_phone'] ?? 'Non spécifié', 1, 1, 'L');
        
        $this->Ln(3); // Réduit l'espace après le tableau
    }

    /**
     * Ajoute les informations de durée et d'estimation des tickets
     */
    private function addDurationAndTicketsInfo($intervention) {
        $this->SetFillColor($this->borderColor[0], $this->borderColor[1], $this->borderColor[2]);
        $this->SetDrawColor($this->borderColor[0], $this->borderColor[1], $this->borderColor[2]);
        
        // Calculer la largeur disponible
        $pageWidth = $this->GetPageWidth();
        $availableWidth = $pageWidth - self::PDF_MARGIN_LEFT - self::PDF_MARGIN_RIGHT;
        $columnWidth = $availableWidth / 3;
        
        // Créer le tableau sur 1 ligne et 3 colonnes
        $this->SetFont(self::PDF_FONT_NAME_MAIN, 'B', 7); // Police plus petite
        
        // Ligne unique
        $this->Cell($columnWidth, 5, 'Durée de l\'intervention', 1, 0, 'L', true);
        $this->Cell($columnWidth, 5, 'Estimation de l\'intervention ticket', 1, 0, 'L', true);
        $this->Cell($columnWidth, 5, 'Estimation de tickets restants', 1, 1, 'L', true);
        $this->SetFont(self::PDF_FONT_NAME_MAIN, '', 7); // Police plus petite
        
        // Durée de l'intervention
        $duration = !empty($intervention['duration']) ? $intervention['duration'] . 'h' : 'Non spécifiée';
        
        // Vérifier si c'est un contrat de type ticket
        $isTicketContract = !empty($intervention['contract_id']) && isContractTicketById($intervention['contract_id']);
        
        // Estimation de l'intervention ticket
        $ticketsUsed = '--';
        if ($isTicketContract && !empty($intervention['tickets_used'])) {
            $ticketsUsed = $intervention['tickets_used'];
        }
        
        // Estimation de tickets restants
        $ticketsRemaining = '--';
        if ($isTicketContract && isset($intervention['tickets_remaining'])) {
            $ticketsRemaining = $intervention['tickets_remaining'];
        }
        
        $this->Cell($columnWidth, 5, $duration, 1, 0, 'L');
        $this->Cell($columnWidth, 5, $ticketsUsed, 1, 0, 'L');
        $this->Cell($columnWidth, 5, $ticketsRemaining, 1, 1, 'L');
        
        $this->Ln(3); // Réduit l'espace après le tableau
    }

    /**
     * Ajoute la description de l'intervention
     */
    private function addDescription($intervention) {
        $this->SetFont(self::PDF_FONT_NAME_MAIN, 'B', 7); // Police plus petite
        $this->Cell(0, 5, 'Description :', 0, 1);
        $this->SetFont(self::PDF_FONT_NAME_MAIN, '', 7); // Police plus petite
        $this->MultiCell(0, 5, $intervention['description'], 0, 'L');
        $this->Ln(2);
    }

    /**
     * Ajoute les solutions apportées
     */
    private function addSolutions($solutions) {
        $this->SetFont(self::PDF_FONT_NAME_MAIN, 'B', 8);
        $this->Cell(0, 7, 'Solution apportée :', 0, 1);
        $this->SetFont(self::PDF_FONT_NAME_MAIN, '', 8);
        foreach ($solutions as $solution) {
            $this->MultiCell(0, 7, $solution['comment'], 0, 'L');
            $this->Ln(2);
        }
    }

    /**
     * Ajoute la section des signatures
     */
    private function addSignatures() {
        // Calculer la position Y pour les signatures (5cm du bas de la page)
        $this->SetY(-50);
        
        // Lignes de signature avec bordure en bas
        $this->SetDrawColor($this->borderColor[0], $this->borderColor[1], $this->borderColor[2]);
        
        // Calculer les largeurs pour centrer les colonnes
        $pageWidth = $this->GetPageWidth();
        $columnWidth = 60; // Largeur de chaque colonne
        $margin = ($pageWidth - (2 * $columnWidth)) / 3; // Marge entre les colonnes
        
        // Première colonne (technicien)
        $this->SetX($margin);
        $this->Cell($columnWidth, 7, 'Signature du technicien', 'B', 0, 'C');
        
        // Deuxième colonne (client)
        $this->SetX($margin * 2 + $columnWidth);
        $this->Cell($columnWidth, 7, 'Signature du client', 'B', 1, 'C');
    }

    /**
     * Ajoute le pied de page
     */
    private function addFooter() {
        $this->SetY(-20); // Positionne le footer plus bas (2cm du bas)
        $this->SetFont(self::PDF_FONT_NAME_MAIN, 'I', 8);
        $this->SetTextColor($this->secondaryColor[0], $this->secondaryColor[1], $this->secondaryColor[2]);
        
        // Calculer les largeurs
        $pageWidth = $this->GetPageWidth();
        $textWidth = 120; // Largeur pour le texte
        $pageNumWidth = 30; // Largeur pour le numéro de page
        
        // Calculer la position X pour centrer le texte
        $textX = ($pageWidth - $textWidth) / 2;
        
        // Positionner le curseur pour le texte centré
        $this->SetX($textX);
        $this->Cell($textWidth, 5, 'VideoSonic - Document généré le ' . date('d/m/Y H:i'), 0, 0, 'C');
        
        // Numéro de page à droite
        $this->SetX($pageWidth - $pageNumWidth - 15); // 15mm de marge droite
        $this->Cell($pageNumWidth, 5, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 1, 'R');
    }

    /**
     * Ajoute les solutions et observations sélectionnées
     */
    private function addSolutionsAndObservations($comments) {
        // Séparer les solutions et les observations
        $solutions = [];
        $observations = [];
        
        foreach ($comments as $comment) {
            if (!empty($comment['is_solution'])) {
                $solutions[] = $comment;
            } else {
                $observations[] = $comment;
            }
        }
        
        // Afficher les observations
        if (!empty($observations)) {
            $this->SetFont(self::PDF_FONT_NAME_MAIN, 'B', 7); // Police plus petite
            $this->Cell(0, 5, 'Observations :', 0, 1);
            $this->SetFont(self::PDF_FONT_NAME_MAIN, '', 7); // Police plus petite
            
            foreach ($observations as $index => $observation) {
                $this->SetFont(self::PDF_FONT_NAME_MAIN, 'B', 6); // Police encore plus petite pour les détails
                $dateFormatted = date('d/m/Y H:i', strtotime($observation['created_at']));
                $userName = $observation['created_by_name'] ?? 'Utilisateur inconnu';
                $this->Cell(0, 4, $dateFormatted . ' par ' . $userName, 0, 1);
                $this->SetFont(self::PDF_FONT_NAME_MAIN, '', 7); // Police plus petite
                $this->MultiCell(0, 4, $observation['comment'], 0, 'L');
                $this->Ln(1);
            }
            $this->Ln(2);
        }
        
        // Afficher les solutions
        if (!empty($solutions)) {
            $this->SetFont(self::PDF_FONT_NAME_MAIN, 'B', 7); // Police plus petite
            $this->Cell(0, 5, 'Solutions :', 0, 1);
            $this->SetFont(self::PDF_FONT_NAME_MAIN, '', 7); // Police plus petite
            
            foreach ($solutions as $index => $solution) {
                $this->SetFont(self::PDF_FONT_NAME_MAIN, 'B', 6); // Police encore plus petite pour les détails
                $dateFormatted = date('d/m/Y H:i', strtotime($solution['created_at']));
                $userName = $solution['created_by_name'] ?? 'Utilisateur inconnu';
                $this->Cell(0, 4, $dateFormatted . ' par ' . $userName, 0, 1);
                $this->SetFont(self::PDF_FONT_NAME_MAIN, '', 7); // Police plus petite
                $this->MultiCell(0, 4, $solution['comment'], 0, 'L');
                $this->Ln(1);
            }
            $this->Ln(2);
        }
    }

    /**
     * Ajoute les images sélectionnées
     */
    private function addSelectedImages($attachments) {
        $imageCount = 0;
        $hasImages = false;
        
        // D'abord, compter les images disponibles
        foreach ($attachments as $attachment) {
            // Vérifier si c'est une image
            $extension = strtolower(pathinfo($attachment['nom_fichier'], PATHINFO_EXTENSION));
            $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'];
            
            if (in_array($extension, $imageExtensions)) {
                $imagePath = __DIR__ . '/../' . $attachment['chemin_fichier'];
                
                if (file_exists($imagePath)) {
                    $hasImages = true;
                    break; // On a trouvé au moins une image, on peut afficher la section
                }
            }
        }
        
        // Ne pas afficher la section s'il n'y a pas d'images
        if (!$hasImages) {
            return;
        }
        
        // Calculer la largeur disponible pour 2 colonnes
        $pageWidth = $this->GetPageWidth();
        $availableWidth = $pageWidth - self::PDF_MARGIN_LEFT - self::PDF_MARGIN_RIGHT;
        $columnWidth = $availableWidth / 2;
        $imageWidth = $columnWidth - 5; // 5mm de marge entre les colonnes
        $imageHeight = 40; // Hauteur fixe pour les images
        
        $currentColumn = 0; // 0 = gauche, 1 = droite
        $startY = $this->GetY();
        
        foreach ($attachments as $attachment) {
            // Vérifier si c'est une image
            $extension = strtolower(pathinfo($attachment['nom_fichier'], PATHINFO_EXTENSION));
            $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'];
            
            if (in_array($extension, $imageExtensions)) {
                $imagePath = __DIR__ . '/../' . $attachment['chemin_fichier'];
                
                if (file_exists($imagePath)) {
                    // Vérifier si on a assez de place pour l'image
                    $currentY = $this->GetY();
                    $pageHeight = $this->GetPageHeight();
                    $marginBottom = self::PDF_MARGIN_BOTTOM + 20; // Marge + footer
                    
                    if ($currentY > $pageHeight - $marginBottom - $imageHeight - 15) { // 15mm pour le texte sous l'image
                        $this->AddPage();
                        $currentColumn = 0; // Reset à la colonne gauche
                        $startY = $this->GetY();
                    }
                    
                    // Calculer la position X selon la colonne
                    $xPosition = self::PDF_MARGIN_LEFT + ($currentColumn * $columnWidth);
                    
                    // Ajouter l'image
                    try {
                        $this->Image($imagePath, $xPosition, $this->GetY(), $imageWidth, $imageHeight);
                        
                        // Ajouter le nom personnalisé sous l'image
                        $this->SetXY($xPosition, $this->GetY() + $imageHeight + 2);
                        $this->SetFont(self::PDF_FONT_NAME_MAIN, '', 6); // Police très petite
                        $imageName = $attachment['nom_personnalise'] ?? $attachment['nom_fichier'];
                        // Tronquer le nom si trop long
                        if (strlen($imageName) > 25) {
                            $imageName = substr($imageName, 0, 22) . '...';
                        }
                        $this->Cell($imageWidth, 4, $imageName, 0, 0, 'C');
                        
                        // Passer à la colonne suivante
                        $currentColumn = ($currentColumn + 1) % 2;
                        
                        // Si on revient à la colonne gauche, descendre d'une ligne
                        if ($currentColumn == 0) {
                            $this->SetXY(self::PDF_MARGIN_LEFT, $this->GetY() + 15); // 15mm d'espacement vertical
                        } else {
                            // Rester à la même hauteur pour la colonne droite
                            $this->SetXY(self::PDF_MARGIN_LEFT + $columnWidth, $startY);
                        }
                        
                        $imageCount++;
                    } catch (Exception $e) {
                        $this->SetFont(self::PDF_FONT_NAME_MAIN, '', 7);
                        $this->Cell(0, 5, 'Erreur lors du chargement de l\'image: ' . ($attachment['nom_personnalise'] ?? $attachment['nom_fichier']), 0, 1);
                    }
                } else {
                    $this->SetFont(self::PDF_FONT_NAME_MAIN, '', 7);
                    $this->Cell(0, 5, 'Fichier non trouvé: ' . ($attachment['nom_personnalise'] ?? $attachment['nom_fichier']), 0, 1);
                }
            }
        }
        
        $this->Ln(3);
    }
} 