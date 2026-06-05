<?php

/**
 * BON D'INTERVENTION PDF
 * Version conforme aux maquettes client
 * TCPDF Required
 */

class InterventionPDF extends TCPDF
{
    private $mainColor = [58, 89, 99];
    private $headerText = [42, 74, 92];
    private $border = [210, 210, 210];
    private $light = [247, 247, 247];

    public function __construct()
    {
        parent::__construct(
            'P',
            'mm',
            'A4',
            true,
            'UTF-8',
            false
        );
        $this->SetCreator('AVision');
        $this->SetAuthor('VIDEOSONIC');

        $this->setPrintHeader(false);
        $this->setPrintFooter(false);

        $this->SetMargins(10, 10, 10);
        $this->SetAutoPageBreak(true, 12);

        $this->SetFont('helvetica', '', 9);

        $this->SetLineWidth(0.2);

        $this->SetDrawColor(
            $this->border[0],
            $this->border[1],
            $this->border[2]
        );
    }

    /**
     * =========================================================
     * GENERATION COMPLETE
     * =========================================================
     */
    public function generateBonIntervention(
        $intervention,
        $comments = [],
        $selectedAttachments = [],
        $technicians = [],
        $equipment = [],
        $replacedParts = []
    ) {

        // =====================================================
        // PAGE 1
        // =====================================================

        $this->AddPage();

        $this->renderHeader($intervention);

        $this->Ln(2);

        $this->renderIdentification($intervention, $technicians);

        $this->Ln(3);

        $this->renderClient($intervention);

        $this->Ln(3);

        $this->renderEquipment($equipment);

        $this->Ln(3);

        // SECTION 4 avec affichage complet sans découpage
        $this->renderDetails($intervention, $comments);

        $this->Ln(3);

        // SECTION 5
        $this->renderParts($replacedParts);

        // =====================================================
        // PAGE 2
        // =====================================================

        $this->AddPage();

        // Header nouvelle page
        $this->renderHeader($intervention);

        $this->Ln(15);

        // Tableau prêt matériel séparé avec bordures complètes
        $this->renderLoanEquipment($equipment);

        $this->Ln(5);

        // Section 6
        $this->renderClosure($intervention, $technicians, $selectedAttachments);

        $this->Ln(2);

        $this->renderFooter();

        return $this;
    }

    /**
     * =========================================================
     * HEADER
     * =========================================================
     */
    private function renderHeader($intervention)
    {
        $width = 190;

        $this->SetFillColor(
            $this->mainColor[0],
            $this->mainColor[1],
            $this->mainColor[2]
        );

        $this->Rect(10, 10, $width, 26, 'F');

        // Titre
        $this->SetTextColor(255, 255, 255);
        $this->SetXY(14, 14);
        $this->SetFont('helvetica', 'B', 20);
        $this->Cell(90, 8, "BON D'INTERVENTION");

        // Sous titre
        $this->SetXY(14, 23);
        $this->SetFont('helvetica', 'I', 10);
        $this->SetTextColor(255, 204, 0);
        $this->Cell(90, 5, 'Généré par AVision  •  VIDEOSONIC');

        // =====================================================
        // BLOC DROITE AVEC SOULIGNEMENTS
        // =====================================================

        $this->SetTextColor(255, 255, 255);

        // N° Ticket
        $ticket = $intervention['reference'] ?? '';
        $this->SetXY(135, 12);
        $this->SetFont('helvetica', '', 10);
        $this->Cell(15, 5, 'N° Ticket :', 0, 0, 'L');
        $this->SetX(135 + 25);
        $this->SetFont('helvetica', '', 10);
        $this->Cell(25, 5, $ticket, 'B', 1, 'R');

        // Version du bon
        $bonVersion = '#VS' . ($intervention['id'] ?? '0000') . '-' . date('ymd');
        $this->SetX(135);
        $this->SetFont('helvetica', 'B', 11);
        $this->SetTextColor(255, 204, 0);
        $this->Cell(40, 5, 'Version du bon :', 0, 0, 'L');
        $this->SetX(135 + 33);
        $this->SetFont('helvetica', 'B', 11);
        $this->Cell(30, 5, $bonVersion, 'B', 1, 'R');

        // Date de création
        $this->SetTextColor(255, 255, 255);
        $this->SetX(135);
        $this->SetFont('helvetica', '', 9);
        $this->Cell(25, 5, 'Date de création :', 0, 0, 'L');

        // Position pour la date
        $this->SetX(135 + 28);
        $this->SetFont('helvetica', 'I', 7);
        $this->SetDrawColor(255, 255, 255);  // Soulignement BLANC uniquement pour cette date

        // Extraire le jour, mois et année
        if (!empty($intervention['created_at'])) {
            $dateObj = new DateTime($intervention['created_at']);
            $day = $dateObj->format('d');
            $month = $dateObj->format('m');
            $year = $dateObj->format('Y');
        } else {
            $day = '__';
            $month = '__';
            $year = '____';
        }

        $slash = '/';

        // Afficher la date avec soulignements blancs
        $this->Cell(5, 5, $day, 'B', 0, 'L');
        $this->Cell(2, 5, $slash, 0, 0, 'L');
        $this->Cell(5, 5, $month, 'B', 0, 'L');
        $this->Cell(2, 5, $slash, 0, 0, 'L');
        $this->Cell(8, 5, $year, 'B', 0, 'L');

        // Restaurer la couleur de bordure par défaut (NOIRE) pour les tableaux
        $this->SetDrawColor($this->border[0], $this->border[1], $this->border[2]);

        $this->Ln();

        $this->SetY(37);
    }
    /**
     * =========================================================
     * TITRE SECTION
     * =========================================================
     */
    private function sectionTitle($title)
    {
        $this->SetFillColor(
            $this->mainColor[0],
            $this->mainColor[1],
            $this->mainColor[2]
        );

        $this->SetTextColor(255, 255, 255);

        $this->SetFont('helvetica', 'B', 11);

        $this->Cell(190, 7, $title, 1, 1, 'L', true);

        $this->SetTextColor(0, 0, 0);
    }

    /**
     * =========================================================
     * CELLULE ENTETE
     * =========================================================
     */
    private function headCell($w, $text, $h = 7)
    {
        $this->SetFillColor(233, 238, 241);

        $this->SetTextColor(
            $this->headerText[0],
            $this->headerText[1],
            $this->headerText[2]
        );

        $this->SetFont('helvetica', 'B', 8.5);

        $this->Cell(
            $w,
            $h,
            $text,
            1,
            0,
            'L',
            true
        );

        $this->SetTextColor(0, 0, 0);
    }

    /**
     * =========================================================
     * CELLULE NORMALE
     * =========================================================
     */
    private function bodyCell($w, $text = '', $h = 7)
    {
        $this->SetFont('helvetica', '', 9);

        $this->Cell(
            $w,
            $h,
            $text,
            1,
            0,
            'L'
        );
    }

    /**
     * =========================================================
     * SECTION 1
     * =========================================================
     */
    private function renderIdentification($intervention, $technicians)
    {
        $this->sectionTitle('1. IDENTIFICATION DU TICKET');

        $w1 = 95;
        $w2 = 95;

        // ligne 1
        $this->headCell($w1, 'Référence ticket interne');
        $this->headCell($w2, 'Réf. client');
        $this->Ln();

        $this->bodyCell($w1, $intervention['reference'] ?? '');
        $this->bodyCell($w2, $intervention['ref_client'] ?? '');
        $this->Ln();

        // ligne 2
        $this->headCell($w1, 'Date / Heure création');
        $this->headCell($w2, 'Date / Heure intervention prévue');
        $this->Ln();

        $created = !empty($intervention['created_at'])
            ? date('d/m/Y H:i', strtotime($intervention['created_at']))
            : '';

        $planned = !empty($intervention['planned_date'])
            ? date('d/m/Y H:i', strtotime($intervention['planned_date']))
            : '';

        $this->bodyCell($w1, $created);
        $this->bodyCell($w2, $planned);
        $this->Ln();

        // ligne 3
        $this->headCell($w1, 'Nom du déclarant');
        $this->headCell($w2, "Nom de l'intervenant");
        $this->Ln();

        $intervenants = '';

        if (!empty($technicians)) {
            $intervenants = implode(
                ', ',
                array_column($technicians, 'full_name')
            );
        }

        $this->bodyCell(
            $w1,
            $intervention['demande_par'] ?? ''
        );

        $this->bodyCell($w2, $intervenants);

        $this->Ln();

        // urgence
        $this->headCell(45, "Niveau d'urgence");

        // Cellule principale
        $this->Cell(145, 8, '', 1, 0);

        $urgence = $intervention['urgence'] ?? '';

        $y = $this->GetY();
        $x = 55;
        // =========================
// Critique
// =========================

        $boxY = $y + 2;
        $textY = $y + 1;

        $criticalBoxX = $x + 5;

        $this->Rect($criticalBoxX, $boxY, 4, 4);

        if ($urgence === 'critical') {
            $this->Line($criticalBoxX, $boxY, $criticalBoxX + 4, $boxY + 4);
            $this->Line($criticalBoxX + 4, $boxY, $criticalBoxX, $boxY + 4);
        }

        $this->SetXY($criticalBoxX + 6, $textY);

        $this->Cell(22, 5, 'Critique', 0, 0);

        // =========================
// Normal
// =========================

        $normalBoxX = $x + 38;

        $this->Rect($normalBoxX, $boxY, 4, 4);

        if ($urgence === 'normal') {
            $this->Line($normalBoxX, $boxY, $normalBoxX + 4, $boxY + 4);
            $this->Line($normalBoxX + 4, $boxY, $normalBoxX, $boxY + 4);
        }

        $this->SetXY($normalBoxX + 6, $textY);

        $this->Cell(20, 5, 'Normal', 0, 0);

        // =========================
// Planifié
// =========================

        $plannedBoxX = $x + 68;

        $this->Rect($plannedBoxX, $boxY, 4, 4);

        if ($urgence === 'planned') {
            $this->Line($plannedBoxX, $boxY, $plannedBoxX + 4, $boxY + 4);
            $this->Line($plannedBoxX + 4, $boxY, $plannedBoxX, $boxY + 4);
        }

        $this->SetXY($plannedBoxX + 6, $textY);

        $this->Cell(
            55,
            5,
            'Planifié / Maintenance préventive',
            0,
            0
        );

        $this->Ln();
    }

    /**
     * =========================================================
     * SECTION 2
     * =========================================================
     */
    private function renderClient($intervention)
    {
        $this->sectionTitle('2. CLIENT & LOCALISATION');

        $w = 47.5;

        // ligne 1 - contrat
        $this->headCell($w, 'N° de contrat');
        $this->headCell($w, 'Type de contrat');
        $this->headCell($w, 'Date fin contrat');
        $this->headCell($w, 'Statut contrat');
        $this->Ln();

        $endDate = !empty($intervention['contract_end_date'])
            ? date('d/m/Y', strtotime($intervention['contract_end_date']))
            : '';

        $this->bodyCell($w, $intervention['contract_name'] ?? '');
        $this->bodyCell($w, $intervention['contract_type_name'] ?? '');
        $this->bodyCell($w, $endDate);
        $this->bodyCell($w, $intervention['contract_status'] ?? '');
        $this->Ln();

        // ligne 2 - client/contact
        $this->headCell(95, 'Nom du client');
        $this->headCell(95, 'Nom du contact');
        $this->Ln();

        $contact = trim(
            ($intervention['contact_first_name'] ?? '') .
            ' ' .
            ($intervention['contact_last_name'] ?? '')
        );

        $this->bodyCell(95, $intervention['client_name'] ?? '');
        $this->bodyCell(95, $contact);
        $this->Ln();

        // ligne 3 - adresse / téléphone
        $this->headCell(95, 'Adresse');
        $this->headCell(95, 'Téléphone du contact');
        $this->Ln();

        $address = trim(
            ($intervention['site_address'] ?? '') .
            ' ' .
            ($intervention['site_postal_code'] ?? '') .
            ' ' .
            ($intervention['site_city'] ?? '')
        );

        $this->MultiCell(95, 12, $address, 1, 'L', false, 0);
        $this->Cell(95, 12, $intervention['contact_phone'] ?? '', 1, 1);

        // ligne 4 - bâtiment / étage / salle
        $this->headCell($w, 'Bâtiment');
        $this->headCell($w, 'Étage');
        $this->headCell(95, 'Salle / Espace');
        $this->Ln();

        $y = $this->GetY();
        $x = $this->GetX(); // = 10

        $this->Cell($w, 22, $intervention['building_name'] ?? '', 1, 0);
        $this->Cell($w, 22, $intervention['floor_level'] ?? '', 1, 0);

        // Cadre salle
        $salleX = $this->GetX();
        $this->Cell(95, 22, '', 1, 0);

        // Contenu salle
        $this->SetXY($salleX + 2, $y + 2);
        $this->SetFont('helvetica', '', 9);
        $this->Cell(50, 5, $intervention['room_name'] ?? '', 0);

        // Mention AVision
        $this->SetXY($salleX + 2, $y + 16);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(40, 4, 'AVision', 0);

        // QR code
        $style = ['border' => 0, 'padding' => 0];
        $this->write2DBarcode(
            $intervention['avision_ref'] ?? 'AVision',
            'QRCODE,L',
            $salleX + 58,
            $y + 4,
            13,
            13,
            $style,
            'N'
        );

        $this->SetXY($salleX + 72, $y + 12);
        $this->SetFont('helvetica', 'I', 7);
        $this->Cell(18, 4, 'Fiche salle', 0);

        $this->Ln(22);
    }

    /**
     * =========================================================
     * SECTION 3
     * =========================================================
     */
    private function renderEquipment($equipment)
    {
        $this->sectionTitle('3. ÉQUIPEMENTS CONCERNÉS');

        $w = 47.5;

        $this->headCell($w, 'Désignation équipement');
        $this->headCell($w, 'Réf. interne AVision');
        $this->headCell($w, 'N° de série');
        $this->headCell($w, 'Marque / Modèle');
        $this->Ln();

        if (!empty($equipment) && is_array($equipment)) {
            foreach ($equipment as $eq) {
                $brand = trim($eq['marque'] ?? '');
                $model = trim($eq['modele'] ?? '');
                $brandModel = trim(
                    $brand .
                    (!empty($brand) && !empty($model) ? ' / ' : '') .
                    $model
                );

                $designation = $eq['designation'] ?? ($eq['modele'] ?? '');
                $refAvision = $eq['ref_avision'] ?? ($eq['reference'] ?? '');
                $numSerie = $eq['numero_serie'] ?? ($eq['serial_number'] ?? '');

                $this->bodyCellTruncated($w, $designation);
                $this->bodyCellTruncated($w, $refAvision);
                $this->bodyCellTruncated($w, $numSerie);
                $this->bodyCellTruncated($w, $brandModel);
                $this->Ln();
            }
        } else {
            for ($i = 0; $i < 3; $i++) {
                $this->Cell($w, 7, '', 1, 0);
                $this->Cell($w, 7, '', 1, 0);
                $this->Cell($w, 7, '', 1, 0);
                $this->Cell($w, 7, '', 1, 1);
            }
        }
    }

    /**
     * Cellule avec troncature automatique et réduction de police si texte long
     */
    private function bodyCellTruncated($w, $text = '', $h = 7)
    {
        // Calculer la largeur max en caractères approximatifs
        // à 9pt helvetica, ~1 char ≈ 2mm → $w mm / 2 = nb chars max
        $maxChars = (int) ($w / 2);

        // Réduire la police si le texte est trop long
        $fontSize = 9;
        if (mb_strlen($text) > $maxChars) {
            $fontSize = 7;
            // Recalculer maxChars avec la police réduite (~1 char ≈ 1.6mm à 7pt)
            $maxCharsSmall = (int) ($w / 1.6);
            if (mb_strlen($text) > $maxCharsSmall) {
                // Tronquer avec ellipse si toujours trop long
                $text = mb_substr($text, 0, $maxCharsSmall - 3) . '...';
            }
        }

        $this->SetFont('helvetica', '', $fontSize);
        $this->Cell($w, $h, $text, 1, 0, 'L');

        // Remettre la police par défaut
        $this->SetFont('helvetica', '', 9);
    }
    /**
     * =========================================================
     * SECTION 4 (COMPLÈTE SANS DÉCOUPAGE)
     * =========================================================
     */
    private function renderDetails($intervention, $comments)
    {
        $this->sectionTitle("4. DÉTAIL DE L'INTERVENTION");

        // =====================================================
// NATURE
// =====================================================

        $this->headCell(45, 'Nature');

        $this->Cell(145, 9, '', 1, 0);

        $natureY = $this->GetY();

        // Boolean : true = distancielle / false = sur site
        $isRemote = (bool) ($intervention['is_remote'] ?? false);

        $surSite = !$isRemote;
        $distancielle = $isRemote;

        // Position verticale commune
        $boxY = $natureY + 2;
        $textY = $natureY + 1;

        // =====================================================
// SUR SITE
// =====================================================

        $surSiteBoxX = 58;

        $this->Rect($surSiteBoxX, $boxY, 4, 4);

        if ($surSite) {

            $this->Line(
                $surSiteBoxX,
                $boxY,
                $surSiteBoxX + 4,
                $boxY + 4
            );

            $this->Line(
                $surSiteBoxX + 4,
                $boxY,
                $surSiteBoxX,
                $boxY + 4
            );
        }

        $this->SetXY($surSiteBoxX + 6, $textY);

        $this->Cell(22, 5, 'Sur site');

        // =====================================================
// DISTANCIELLE
// =====================================================

        $remoteBoxX = 90;

        $this->Rect($remoteBoxX, $boxY, 4, 4);

        if ($distancielle) {

            $this->Line(
                $remoteBoxX,
                $boxY,
                $remoteBoxX + 4,
                $boxY + 4
            );

            $this->Line(
                $remoteBoxX + 4,
                $boxY,
                $remoteBoxX,
                $boxY + 4
            );
        }

        $this->SetXY($remoteBoxX + 6, $textY);

        $this->Cell(30, 5, 'Distancielle');

        // margin-bottom
        $this->Ln(12);

        // =====================================================
        // HORAIRES
        // =====================================================

        $w = 47.5;

        $this->headCell($w, 'Heure début intervention');
        $this->headCell($w, 'Heure fin intervention');
        $this->headCell($w, 'Total en tickets');
        $this->headCell($w, 'Tickets restant');

        $this->Ln();

        // Valeurs
        $startTime =
            !empty($intervention['planned_date'])
            ? $intervention['planned_date']
            : '';

        $endTime =
            !empty($intervention['end_date'])
            ? $intervention['end_date']
            : '';

        $ticketsUsed =
            $intervention['tickets_used'] ?? '0';

        $ticketsRemaining =
            $intervention['tickets_remaining'] ?? '0';

        // Heure début
        $this->Cell(
            $w,
            10,
            $startTime,
            1,
            0,
            'C'
        );

        // Heure fin
        $this->Cell(
            $w,
            10,
            $endTime,
            1,
            0,
            'C'
        );

        // Total tickets
        $this->Cell(
            $w,
            10,
            $ticketsUsed,
            1,
            0,
            'C'
        );

        // Tickets restant
        $this->Cell(
            $w,
            10,
            $ticketsRemaining,
            1,
            1,
            'C'
        );

        // =====================================================
        // DESCRIPTION
        // =====================================================

        $this->Cell(190, 35, '', 1);

        $y = $this->GetY();

        $this->SetXY(13, $y + 2);

        $this->SetFont('helvetica', 'B', 10);

        $this->Cell(
            60,
            5,
            'Description du problème :',
            0
        );

        $this->SetXY(13, $y + 10);

        $this->SetFont('helvetica', '', 9);

        $this->MultiCell(
            180,
            5,
            $intervention['description'] ?? '',
            0,
            'L'
        );

        $this->Ln();

        // =====================================================
        // COMMENTAIRES
        // =====================================================

        $this->Cell(190, 40, '', 1);

        $y = $this->GetY();

        $this->SetXY(13, $y + 2);

        $this->SetFont('helvetica', 'B', 10);

        $this->Cell(
            80,
            5,
            'Solution apportée / Commentaires :',
            0
        );

        $commentText = '';

        if (!empty($comments)) {

            foreach ($comments as $comment) {

                $commentText .=
                    ($comment['comment'] ?? '') .
                    "\n";
            }
        }

        $this->SetXY(13, $y + 10);

        $this->SetFont('helvetica', '', 9);

        $this->MultiCell(
            180,
            5,
            trim($commentText),
            0,
            'L'
        );

        $this->Ln();
    }

    /**
     * =========================================================
     * SECTION 5
     * PIÈCES REMPLACÉES
     * =========================================================
     */
    private function renderParts($replacedParts)
    {
        // =====================================================
        // TITRE SECTION
        // =====================================================

        $this->sectionTitle('5. PIÈCES REMPLACÉES & MATÉRIEL PRÊTÉ');

        // =====================================================
        // TABLEAU PIÈCES REMPLACÉES
        // =====================================================

        // Définir les largeurs des colonnes
        $w1 = 60;
        $w2 = 40;
        $w3 = 40;
        $w4 = 30;
        $w5 = 20;

        $this->headCell($w1, 'Désignation / Modèle');
        $this->headCell($w2, 'Version précédente');
        $this->headCell($w3, 'Version installée');
        $this->headCell($w4, 'N° série');
        $this->headCell($w5, 'Qté');

        $this->Ln();

        if (!empty($replacedParts) && is_array($replacedParts)) {
            foreach ($replacedParts as $part) {
                $designation = $part['designation'] ?? '-';

                $oldVersion = $part['old_version'] ?? '-';

                $newVersion = $part['new_version'] ?? '-';

                $serialNumber = $part['serial_number'] ?? '-';
                $quantity = $part['quantity'] ?? 1;

                $this->bodyCell($w1, $designation, 12);
                $this->bodyCell($w2, $oldVersion, 12);
                $this->bodyCell($w3, $newVersion, 12);
                $this->bodyCell($w4, $serialNumber, 12);
                $this->bodyCell($w5, $quantity, 12);
                $this->Ln();
            }
        } else {
            // Lignes vides
            for ($i = 0; $i < 3; $i++) {
                $this->Cell($w1, 12, '', 1, 0);
                $this->Cell($w2, 12, '', 1, 0);
                $this->Cell($w3, 12, '', 1, 0);
                $this->Cell($w4, 12, '', 1, 0);
                $this->Cell($w5, 12, '', 1, 1);
            }
        }
    }

    /**
     * =========================================================
     * TABLEAU INTERMÉDIAIRE + PRÊT DE MATÉRIEL
     * =========================================================
     */
    private function renderLoanEquipment($equipment)
    {
        // Collé juste sous le header
        $startY = 36;

        // =====================================================
        // TABLEAU INTERMÉDIAIRE VIDE
        // 2 LIGNES × 3 COLONNES
        // =====================================================

        $col1 = 95;
        $col2 = 47.5;
        $col3 = 47.5;

        $rowHeight = 12;

        // Bordures
        $this->SetDrawColor(
            $this->border[0],
            $this->border[1],
            $this->border[2]
        );

        // =========================
        // LIGNE 1
        // =========================

        $this->Rect(
            10,
            $startY,
            $col1,
            $rowHeight
        );

        $this->Rect(
            10 + $col1,
            $startY,
            $col2,
            $rowHeight
        );

        $this->Rect(
            10 + $col1 + $col2,
            $startY,
            $col3,
            $rowHeight
        );

        // =========================
        // LIGNE 2
        // =========================

        $this->Rect(
            10,
            $startY + $rowHeight,
            $col1,
            $rowHeight
        );

        $this->Rect(
            10 + $col1,
            $startY + $rowHeight,
            $col2,
            $rowHeight
        );

        $this->Rect(
            10 + $col1 + $col2,
            $startY + $rowHeight,
            $col3,
            $rowHeight
        );

        // =====================================================
        // TABLEAU PRÊT DE MATÉRIEL
        // =====================================================

        $loanY = $startY + ($rowHeight * 2);

        // Fond beige principal
        $this->SetFillColor(248, 243, 226);

        // Cadre principal
        $this->Rect(10, $loanY, 190, 22, 'FD');

        // Bordure principale
        $this->Rect(10, $loanY, 190, 22, 'D');

        // =====================================================
        // COLONNE 1
        // =====================================================

        $this->Rect(10, $loanY, 45, 22, 'FD');
        $this->Rect(10, $loanY, 45, 22, 'D');

        $this->SetXY(12, $loanY + 4);

        $this->SetFont('helvetica', 'B', 9);

        $this->SetTextColor(
            $this->headerText[0],
            $this->headerText[1],
            $this->headerText[2]
        );

        $this->Cell(
            35,
            5,
            'Prêt de matériel',
            0,
            0,
            'L'
        );

        // =====================================================
        // COLONNE 2
        // =====================================================

        $this->Rect(55, $loanY, 42, 22, 'FD');
        $this->Rect(55, $loanY, 42, 22, 'D');

        $this->SetFont('helvetica', '', 9);
        $this->SetTextColor(0, 0, 0);

        // Case Oui
        $this->Rect(60, $loanY + 5, 4, 4);
        $this->SetXY(66, $loanY + 4);
        $this->Cell(12, 5, 'Oui', 0, 0);

        // Case Non
        $this->Rect(78, $loanY + 5, 4, 4);
        $this->SetXY(84, $loanY + 4);
        $this->Cell(12, 5, 'Non', 0, 0);
        // =====================================================
        // COLONNE 3
        // =====================================================

        $this->Rect(97, $loanY, 103, 22, 'FD');
        $this->Rect(97, $loanY, 103, 22, 'D');

        $this->SetXY(100, $loanY + 3);

        $this->SetFont('helvetica', '', 9);

        $this->Cell(
            90,
            5,
            'Désignation / N° série du matériel prêté :',
            0,
            0,
            'L'
        );

        // Ligne d'écriture
        $this->SetDrawColor(150, 150, 150);

        $this->Line(
            103,
            $loanY + 16,
            190,
            $loanY + 16
        );

        // Restaurer couleur bordure
        $this->SetDrawColor(
            $this->border[0],
            $this->border[1],
            $this->border[2]
        );

        // Position suivante
        $this->SetY($loanY + 28);
    }
    /**
     * =========================================================
     * SECTION 6 — CLÔTURE & SIGNATURES
     * Conforme à la maquette client
     * =========================================================
     */
    private function renderClosure($intervention, $technicians, $selectedAttachments)
    {

        $this->sectionTitle('6. CLÔTURE & SIGNATURES');

        // =====================================================
        // BLOC RETOUR + PHOTOS
        // =====================================================

        $topY = $this->GetY();

        // Cadres principaux
        $this->Rect(10, $topY, 95, 22);
        $this->Rect(105, $topY, 95, 22);

        // -------------------------
        // RETOUR NECESSAIRE
        // -------------------------

        $this->SetXY(14, $topY + 4);

        $this->SetFont('helvetica', 'B', 9);

        $this->Cell(50, 5, 'Retour nécessaire', 0, 1);

        $this->SetFont('helvetica', '', 9);
        $needsCompletion = $intervention['needs_completion'] ?? 0;

        // Oui
        $this->Rect(14, $topY + 11, 4, 4);
        if ($needsCompletion == 1) {
            // Cocher la case Oui
            $this->Line(14, $topY + 11, 18, $topY + 15);
            $this->Line(18, $topY + 11, 14, $topY + 15);
        }
        $this->SetXY(20, $topY + 10);
        $this->Cell(70, 5, 'Oui – Motif : __________________');

        // Non
        $this->Rect(14, $topY + 17, 4, 4);
        if ($needsCompletion == 0) {
            // Cocher la case Non (intervention clôturée)
            $this->Line(14, $topY + 17, 18, $topY + 21);
            $this->Line(18, $topY + 17, 14, $topY + 21);
        }
        $this->SetXY(20, $topY + 16);
        $this->Cell(80, 5, 'Non – Intervention clôturée');
        // -------------------------
        // PHOTOS JOINTES
        // -------------------------
        $this->SetXY(109, $topY + 4);

        $this->SetFont('helvetica', 'B', 9);

        $this->Cell(45, 5, 'Photos jointes', 0, 1);

        $this->SetFont('helvetica', '', 9);

        // Compter le nombre de photos jointes
        $photosCount = count($selectedAttachments);
        $hasPhotos = ($photosCount > 0);

        // Case Oui
        $this->Rect(109, $topY + 11, 4, 4);
        if ($hasPhotos) {
            // Cocher la case Oui
            $this->Line(109, $topY + 11, 113, $topY + 15);
            $this->Line(113, $topY + 11, 109, $topY + 15);
        }

        $this->SetXY(115, $topY + 10);
        $this->Cell(17, 5, 'Oui – Nb : ', 0, 0);

        // Afficher le nombre avec soulignement
        if ($hasPhotos) {
            $this->SetDrawColor(0, 0, 0);
            $this->Cell(5, 3, $photosCount, 'B', 0, 'L');
        } else {
            $this->SetDrawColor(0, 0, 0);
            $this->SetFont('helvetica', 'I', 7);
            $this->Cell(10, 5, '', 'B', 0, 'L');
        }

        // Case Non
        $this->Rect(153, $topY + 11, 4, 4);
        if (!$hasPhotos) {
            // Cocher la case Non
            $this->Line(153, $topY + 11, 157, $topY + 15);
            $this->Line(157, $topY + 11, 153, $topY + 15);
        }

        $this->SetXY(145, $topY + 10);
        $this->Cell(20, 5, 'Non', 0, 0);

        // =====================================================
        // BLOC SIGNATURES
        // =====================================================

        $signY = $topY + 22;

        // Cadres signatures
        $this->Rect(10, $signY, 95, 52);
        $this->Rect(105, $signY, 95, 52);

        // Fond beige client
        $this->SetFillColor(248, 243, 226);

        $this->Rect(105, $signY, 95, 52, 'F');

        // Reborde après fill
        $this->Rect(105, $signY, 95, 52);

        // -------------------------
        // SIGNATURE TECHNICIEN
        // -------------------------

        $this->SetXY(14, $signY + 4);
        $this->SetFont('helvetica', 'B', 9);
        $this->SetTextColor(42, 74, 92);
        $this->Cell(60, 5, 'Signature technicien');

        // Récupération des noms des techniciens
        $intervenants = '';

        if (!empty($technicians)) {
            $intervenants = implode(
                ', ',
                array_column($technicians, 'first_name')
            );
        }
        $this->SetFont('helvetica', '', 9);
        $this->SetTextColor(0, 0, 0);

        // Nom du technicien (ligne 1)
        $this->SetXY(14, $signY + 12);
        $this->SetFont('helvetica', '', 9);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(10, 5, "Nom : ", 0, 0, 'L');
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(60, 5, $intervenants, 'B', 0, 'L');

        // Date (ligne 2, en dessous du nom)
        $this->SetXY(14, $signY + 19);
        $this->SetFont('helvetica', '', 9);
        $this->Cell(10, 5, "Date : ", 0, 0, 'L');

        // Date ligne soulignée
        $this->SetFont('helvetica', 'I', 7);
        $this->SetDrawColor(0, 0, 0);

        $day = date('d');
        $month = date('m');
        $year = date('Y');
        $slash = '/';

        $this->Cell(4, 5, $day, 'B', 0, 'L');
        $this->Cell(2, 5, $slash, 0, 0, 'L');
        $this->Cell(4, 5, $month, 'B', 0, 'L');
        $this->Cell(2, 5, $slash, 0, 0, 'L');
        $this->Cell(6, 5, $year, 'B', 0, 'L');

        // Restaurer la couleur de bordure par défaut (NOIRE)
        $this->SetDrawColor($this->border[0], $this->border[1], $this->border[2]);
        // SIGNATURE CLIENT
        // -------------------------

        $this->SetXY(109, $signY + 4);
        $this->SetFont('helvetica', 'B', 9);
        $this->SetTextColor(42, 74, 92);
        $this->Cell(70, 5, 'Bon pour accord client');

        // Nom du client (ligne 1)
        $this->SetXY(109, $signY + 12);
        $this->SetFont('helvetica', '', 9);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(10, 5, "Nom : ", 0, 0, 'L');
        $this->SetFont('helvetica', 'I', 8);
        $clientName = $intervention['client_name'] ?? '____________________';
        if (empty($clientName) || $clientName == '') {
            $clientName = '____________________';
        }
        $this->Cell(60, 5, $clientName, 'B', 0, 'L');

        // Date (ligne 2, en dessous du nom)
        $this->SetXY(109, $signY + 19);
        $this->SetFont('helvetica', '', 9);
        $this->Cell(10, 5, "Date : ", 0, 0, 'L');

        // Date ligne soulignée
        $this->SetFont('helvetica', 'I', 7);
        $this->SetDrawColor(0, 0, 0);

        $this->Cell(4, 5, $day, 'B', 0, 'L');
        $this->Cell(2, 5, $slash, 0, 0, 'L');
        $this->Cell(4, 5, $month, 'B', 0, 'L');
        $this->Cell(2, 5, $slash, 0, 0, 'L');
        $this->Cell(6, 5, $year, 'B', 0, 'L');

        // Mention vérification
        $this->SetXY(111, $signY + 31);
        $this->SetFont('helvetica', 'I', 8);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(70, 4, '☐ Sous réserve de vérification');

        // Mention légale
        $this->SetXY(111, $signY + 36);
        $this->SetTextColor(120, 120, 120);
        $this->Cell(80, 4, 'La signature vaut acceptation des travaux réalisés.');

        // Mention vérification
        $this->SetXY(111, $signY + 31);

        $this->SetFont('helvetica', 'I', 8);

        $this->Cell(
            70,
            4,
            '☐ Sous réserve de vérification'
        );

        // Mention légale
        $this->SetXY(111, $signY + 36);

        $this->SetTextColor(120, 120, 120);

        $this->Cell(
            80,
            4,
            'La signature vaut acceptation des travaux réalisés.'
        );

        // =====================================================
        // BAS : QR + INFOS
        // =====================================================

        $bottomY = $signY + 52;

        // Bloc gauche
        $this->Rect(10, $bottomY, 95, 26);

        // Bloc droite bleu clair
        $this->SetFillColor(236, 242, 244);

        $this->Rect(105, $bottomY, 95, 26, 'F');

        $this->Rect(105, $bottomY, 95, 26);

        // QR CODE
        $style = [
            'border' => 0,
            'padding' => 0,
            'fgcolor' => [70, 90, 100],
            'bgcolor' => false
        ];

        $this->write2DBarcode(
            'AVision Ticket',
            'QRCODE,L',
            18,
            $bottomY + 4,
            12,
            12,
            $style,
            'N'
        );

        // Texte QR
        $this->SetXY(34, $bottomY + 16);

        $this->SetFont('helvetica', 'I', 8);

        $this->SetTextColor(70, 70, 70);

        $this->Cell(
            60,
            4,
            'Accès à la fiche ticket AVision (lecture client)'
        );

        // Texte droite
        $this->SetXY(111, $bottomY + 4);

        $this->SetFont('helvetica', 'I', 7);

        $this->SetTextColor(60, 80, 95);

        $this->Cell(
            80,
            4,
            'Bon généré par AVision Pro – Document confidentiel VIDEOSONIC'
        );

        $this->SetXY(111, $bottomY + 10);

        // Couleur texte noire
        $this->SetTextColor(0, 0, 0);

        $heure = date('H');
        $minute = date('i');
        $version = '1.0';

        // "Généré le :" sans soulignement
        $this->Cell(22, 5, 'Généré le :', 0, 0, 'L');

        // Date soulignée (format JJ/MM/AAAA)
        $this->Cell(4, 1, $day, 'B', 0, 'L');
        $this->Cell(1.5, 1, $slash);
        $this->Cell(4, 1, $month, 'B', 0, 'L');
        $this->Cell(1.5, 1, $slash);
        $this->Cell(6, 1, $year, 'B', 0, 'L');
        // "à" sans soulignement
        $this->Cell(4, 1, ' à ', 0, 0, 'L');

        // Heure soulignée (HH)
        $this->Cell(6, 1, $heure . " h", 'B', 0, 'L');
        // Minute soulignée (mm)
        $this->Cell(6, 1, $minute . "'", 'B', 0, 'L');
        // " – Version V" sans soulignement
        $this->Cell(15, 5, ' – Version V', 0, 0, 'L');

        // Version soulignée
        $this->Cell(10, 1, $version, 'B', 0, 'L');
        $this->SetY($bottomY + 28);
    }

    /**
     * =========================================================
     * FOOTER CONFORME À LA MAQUETTE CLIENT
     * =========================================================
     */
    private function renderFooter()
    {
        // Position fixe en bas
        $this->SetY(-16);

        // Couleur ligne
        $this->SetDrawColor(170, 170, 170);

        // Ligne horizontale
        $this->Line(
            13,
            $this->GetY(),
            197,
            $this->GetY()
        );

        // Petit espace
        $this->Ln(1.5);

        // Style texte footer
        $this->SetFont('helvetica', '', 7);

        $this->SetTextColor(55, 55, 55);

        // Première ligne
        $footerLine1 =
            "VIDEOSONIC | 326 rue Henri Becquerel Porte B2 – Parc d'activités des Portes de l'Oise – 60230 CHAMBLY";

        $this->Cell(
            0,
            3,
            $footerLine1,
            0,
            1,
            'C'
        );

        // Deuxième ligne
        $footerLine2 =
            "Tél : 01 75 01 60 40 | info@videosonic.fr | www.videosonic.fr | SARL 100 000€ – RCS COMPIÈGNE 437 689 185 – APE 4778C";

        $this->Cell(
            0,
            3,
            $footerLine2,
            0,
            1,
            'C'
        );
    }

    /**
     * =========================================================
     * FOOTER TCPDF AUTO
     * =========================================================
     */
    public function Footer()
    {
        $this->renderFooter();
    }
}