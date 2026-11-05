$base = 'http://127.0.0.1:8000/api/v1'

function Appel($methode, $chemin, $corps = $null, $token = $null) {
    if ($chemin -like 'auth/*') { Start-Sleep -Seconds 7 }
    $entetes = @{ Accept = 'application/json' }
    if ($token) { $entetes['Authorization'] = "Bearer $token" }
    try {
        if ($null -ne $corps) {
            return Invoke-RestMethod -Method $methode -Uri "$base/$chemin" -Headers $entetes -ContentType 'application/json' -Body ($corps | ConvertTo-Json -Depth 5)
        }
        return Invoke-RestMethod -Method $methode -Uri "$base/$chemin" -Headers $entetes
    } catch {
        $code = 0
        $texte = ''
        if ($_.Exception.Response) {
            $code = [int]$_.Exception.Response.StatusCode
            try {
                $lecteur = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
                $texte = $lecteur.ReadToEnd()
            } catch { $texte = $_.ErrorDetails.Message }
        }
        Write-Host "  -> REFUSE ($code) sur $chemin : $texte" -ForegroundColor Yellow
        return $null
    }
}

function CodeRecu($destination, $usage) {
    $motif = "Code $usage pour " + [regex]::Escape($destination) + ' :'
    $ligne = (Select-String -Path 'storage\logs\laravel.log' -Pattern $motif | Select-Object -Last 1).Line
    if (-not $ligne) {
        Write-Host "  -> Aucun code '$usage' trouve dans le log pour $destination" -ForegroundColor Red
        return ''
    }
    $code = ($ligne -split ' : ')[-1].Trim()
    Write-Host "  (code $usage lu pour $destination : $code)" -ForegroundColor DarkGray
    return $code
}

function Voir($titre, $objet) {
    Write-Host "`n=== $titre ===" -ForegroundColor Cyan
    $objet | ConvertTo-Json -Depth 6
}

# Inscrit le compte, ou le connecte s'il existe deja
function Acces($destination, $nom, $appareil) {
    Appel POST 'auth/code' @{ purpose = 'register'; channel = 'sms'; destination = $destination } | Out-Null
    $corps = @{ channel = 'sms'; destination = $destination; code = (CodeRecu $destination 'register'); name = $nom; country_code = 'CM'; accept_terms = $true; accept_privacy = $true; device = $appareil }
    $r = Appel POST 'auth/register' $corps
    if ($r) { return $r }
    Appel POST 'auth/code' @{ purpose = 'login'; channel = 'sms'; destination = $destination } | Out-Null
    $corps = @{ channel = 'sms'; destination = $destination; code = (CodeRecu $destination 'login'); device = $appareil }
    return (Appel POST 'auth/login' $corps)
}

$appareilMarie = @{ install_id = 'abcdefghijklmnop1234'; label = 'Test'; platform = 'android' }
$appareilPaul = @{ install_id = 'zyxwvutsrqponmlk9876'; label = 'Test Paul'; platform = 'android' }

# 1. Marie
$marie = Acces '+237600000000' 'Marie Ngono' $appareilMarie
Voir 'Connexion de Marie (attendu : token)' $marie
$tm = $marie.token
Voir 'Moi (attendu : Marie Ngono)' (Appel GET 'me' $null $tm)

# 2. Creation d'une enfant de 15 ans
$corpsEnfant = @{ first_name = 'Aicha'; birth_date = '2011-03-14'; country_code = 'CM'; system_code = 'fr'; level_code = '3eme'; track_code = 'Generale'; pin = '1234'; role = 'payer' }
$enfant = Appel POST 'children' $corpsEnfant $tm
Voir 'Enfant creee (attendu : Aicha, age 15)' $enfant
$idEnfant = $enfant.child.id
Voir 'Liste de Marie (attendu : 1 enfant)' (Appel GET 'children' $null $tm)

# 3. Refus attendus : trop jeune, adulte
$trop = $corpsEnfant.Clone(); $trop.birth_date = '2020-01-01'
Voir 'Test : enfant trop jeune (doit etre refuse)' (Appel POST 'children' $trop $tm)
$adulte = $corpsEnfant.Clone(); $adulte.birth_date = '2000-01-01'
Voir 'Test : adulte (doit etre refuse)' (Appel POST 'children' $adulte $tm)

# 4. Invitation de Paul
$corps = @{ channel = 'sms'; destination = '+237611111111'; name = 'Paul Ngono'; role = 'follower' }
Voir 'Invitation envoyee (attendu : pending)' (Appel POST "children/$idEnfant/guardians" $corps $tm)

# 5. Paul s'inscrit puis accepte
$paul = Acces '+237611111111' 'Paul Ngono' $appareilPaul
Voir 'Inscription de Paul (attendu : token)' $paul
$tp = $paul.token
$inv = Appel GET 'invitations' $null $tp
Voir 'Invitations de Paul (attendu : 1, enfant Aicha)' $inv
if ($inv -and $inv.invitations -and $inv.invitations.Count -gt 0) {
    $idInvitation = $inv.invitations[0].id
    Voir 'Acceptation' (Appel POST "invitations/$idInvitation/accept" $null $tp)
} else {
    Write-Host '  -> Aucune invitation recue, acceptation sautee.' -ForegroundColor Red
}
Voir 'Liste de Paul (attendu : Aicha, role follower)' (Appel GET 'children' $null $tp)
Voir 'Detail vu par Marie (attendu : 2 responsables)' (Appel GET "children/$idEnfant" $null $tm)

# 6. Refus attendus : troisieme responsable, invitation par Paul
$tiers = @{ channel = 'sms'; destination = '+237622222222'; name = 'Tiers Test'; role = 'follower' }
Voir 'Test : 3e responsable (doit etre refuse)' (Appel POST "children/$idEnfant/guardians" $tiers $tm)
Voir 'Test : Paul invite (doit etre refuse, 403)' (Appel POST "children/$idEnfant/guardians" $tiers $tp)