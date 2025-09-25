// Fichier: js/preview_direct.js - Version sans jQuery (JavaScript natif)

console.log('=== FICHIER JAVASCRIPT CHARGÉ (SANS JQUERY) ===');

document.addEventListener('DOMContentLoaded', function() {
    console.log('=== DOM CONTENT LOADED ===');
    initPreviewFeatures();
});

function initPreviewFeatures() {
    console.log('=== INITIALISATION SANS JQUERY ===');
    
    // Test des éléments présents
    setTimeout(function() {
        console.log('=== TEST ÉLÉMENTS PAGE ===');
        console.log('- Bouton apply-changes-btn:', document.querySelectorAll('#apply-changes-btn').length);
        console.log('- Inputs new-name-input:', document.querySelectorAll('.new-name-input').length);
        console.log('- Inputs current-name-input:', document.querySelectorAll('.current-name-input').length);
        console.log('- Bouton copy-sql-btn:', document.querySelectorAll('#copy-sql-btn').length);
        console.log('- Textarea sql-code:', document.querySelectorAll('#sql-code').length);
        
        var applyBtn = document.getElementById('apply-changes-btn');
        if (applyBtn) {
            console.log('✅ Bouton apply trouvé');
            console.log('- categoryid:', applyBtn.getAttribute('data-categoryid'));
            console.log('- system-type:', applyBtn.getAttribute('data-system-type'));
            console.log('- HTML:', applyBtn.outerHTML.substring(0, 200));
        } else {
            console.error('❌ Bouton apply non trouvé');
        }
    }, 1000);
    
    // *** GESTIONNAIRE BOUTON APPLIQUER ***
    document.addEventListener('click', function(e) {
        if (e.target && e.target.id === 'apply-changes-btn') {
            console.log('=== CLIC DÉTECTÉ SUR BOUTON APPLIQUER ===');
            e.preventDefault();
            
            var btn = e.target;
            console.log('Bouton cliqué:', btn);
            
            var categoryid = btn.getAttribute('data-categoryid');
            var systemType = btn.getAttribute('data-system-type');
            
            console.log('=== DONNÉES BOUTON ===');
            console.log('- categoryid:', categoryid);
            console.log('- systemType:', systemType);
            
            if (!categoryid || !systemType) {
                console.error('❌ DONNÉES MANQUANTES');
                alert('Erreur: Données manquantes du bouton\n- categoryid: ' + categoryid + '\n- systemType: ' + systemType);
                return;
            }
            
            console.log('=== COLLECTE DES MODIFICATIONS ===');
            
            var changes = [];
            var newNameInputs = document.querySelectorAll('.new-name-input');
            var currentNameInputs = document.querySelectorAll('.current-name-input');
            
            console.log('- Inputs new-name trouvés:', newNameInputs.length);
            console.log('- Inputs current-name trouvés:', currentNameInputs.length);
            
            if (newNameInputs.length === 0) {
                console.error('❌ AUCUN INPUT new-name-input');
                alert('Erreur: Aucun champ de saisie trouvé.');
                return;
            }
            
            // Parcourir tous les inputs new-name
            for (var i = 0; i < newNameInputs.length; i++) {
                var input = newNameInputs[i];
                var roomId = input.getAttribute('data-room-id');
                var newName = input.value.trim();
                
                console.log('Input ' + i + '- Room ID:', roomId, 'New name:', newName);
                
                // Trouver l'input current-name correspondant
                var currentNameInput = document.querySelector('.current-name-input[data-room-id="' + roomId + '"]');
                var currentName = currentNameInput ? currentNameInput.value.trim() : '';
                
                console.log('- Current name:', currentName);
                
                if (roomId && newName && newName !== currentName) {
                    changes.push({
                        room_id: parseInt(roomId),
                        new_name: newName,
                        current_name: currentName
                    });
                    console.log('✅ Modification ajoutée pour room', roomId);
                } else {
                    console.log('⚠️ Modification ignorée pour room', roomId);
                }
            }
            
            console.log('=== RÉSULTAT COLLECTE ===');
            console.log('Nombre total de modifications:', changes.length);
            console.log('Détail des modifications:', changes);
            
            if (changes.length === 0) {
                alert('Aucune modification détectée.\n\nVérifiez que vous avez modifié au moins un nom.');
                return;
            }
            
            // Confirmation
            var confirmMsg = 'Appliquer ' + changes.length + ' modification(s) ?\n\n';
            confirmMsg += 'Cette action est irréversible.';
            
            if (!confirm(confirmMsg)) {
                console.log('Utilisateur a annulé');
                return;
            }
            
            console.log('=== DÉBUT REQUÊTE AJAX ===');
            
            // Désactiver le bouton
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Application...';
            
            // Construction d'URL (chercher M.cfg ou utiliser window.location)
            var ajaxUrl;
            if (typeof M !== 'undefined' && M.cfg && M.cfg.wwwroot) {
                ajaxUrl = M.cfg.wwwroot + '/local/batchpreview/apply.php';
            } else {
                ajaxUrl = window.location.origin + '/local/batchpreview/apply.php';
            }
            
            console.log('URL AJAX:', ajaxUrl);
            
            var requestData = {
                categoryid: parseInt(categoryid),
                system_type: systemType,
                changes: changes
            };
            
            console.log('Données requête:', requestData);
            
            // Requête AJAX avec XMLHttpRequest natif
            var xhr = new XMLHttpRequest();
            xhr.open('POST', ajaxUrl, true);
            xhr.setRequestHeader('Content-Type', 'application/json; charset=utf-8');
            xhr.timeout = 45000;
            
            xhr.onload = function() {
                console.log('=== RÉPONSE REÇUE ===');
                console.log('Status:', xhr.status);
                console.log('Response text:', xhr.responseText);
                
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        console.log('Response parsed:', response);
                        
                        if (response && response.success) {
                            var message = '✅ Succès !\n\n' + (response.message || 'Modifications appliquées');
                            alert(message);
                            setTimeout(function() {
                                window.location.reload();
                            }, 2000);
                        } else {
                            var errorMsg = '❌ Erreurs :\n\n';
                            if (response && response.error) {
                                errorMsg += response.error;
                            } else if (response && response.errors) {
                                errorMsg += response.errors.join('\n');
                            } else {
                                errorMsg += 'Erreur inconnue';
                            }
                            alert(errorMsg);
                        }
                    } catch (e) {
                        console.error('Erreur parsing JSON:', e);
                        alert('❌ Erreur: Réponse serveur invalide');
                    }
                } else {
                    console.error('Erreur HTTP:', xhr.status);
                    var errorMsg = '❌ Erreur HTTP: ';
                    if (xhr.status === 404) {
                        errorMsg += 'Fichier apply.php non trouvé';
                    } else if (xhr.status === 500) {
                        errorMsg += 'Erreur serveur interne';
                    } else if (xhr.status === 403) {
                        errorMsg += 'Accès refusé';
                    } else {
                        errorMsg += 'Code ' + xhr.status;
                    }
                    alert(errorMsg);
                }
                
                // Réactiver le bouton
                btn.disabled = false;
                btn.innerHTML = '<i class="fa fa-check"></i> Appliquer les modifications';
            };
            
            xhr.onerror = function() {
                console.error('=== ERREUR RÉSEAU ===');
                alert('❌ Erreur de réseau. Vérifiez votre connexion.');
                btn.disabled = false;
                btn.innerHTML = '<i class="fa fa-check"></i> Appliquer les modifications';
            };
            
            xhr.ontimeout = function() {
                console.error('=== TIMEOUT ===');
                alert('❌ Timeout. La requête a pris trop de temps.');
                btn.disabled = false;
                btn.innerHTML = '<i class="fa fa-check"></i> Appliquer les modifications';
            };
            
            // Envoyer la requête
            console.log('Envoi de la requête...');
            xhr.send(JSON.stringify(requestData));
        }
    });
    
    // *** GESTIONNAIRE COPIER SQL ***
    document.addEventListener('click', function(e) {
        if (e.target && e.target.id === 'copy-sql-btn') {
            console.log('=== CLIC COPIER SQL ===');
            var sqlTextarea = document.getElementById('sql-code');
            if (sqlTextarea) {
                var sqlText = sqlTextarea.value;
                console.log('Texte SQL longueur:', sqlText.length);
                
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(sqlText).then(function() {
                        console.log('Copie réussie');
                        var btn = e.target;
                        var originalText = btn.textContent;
                        btn.textContent = 'Copié !';
                        setTimeout(function() {
                            btn.textContent = originalText;
                        }, 2000);
                    }).catch(function(err) {
                        console.error('Erreur copie:', err);
                        alert('Erreur lors de la copie');
                    });
                } else {
                    // Fallback
                    sqlTextarea.select();
                    try {
                        var success = document.execCommand('copy');
                        if (success) {
                            var btn = e.target;
                            var originalText = btn.textContent;
                            btn.textContent = 'Copié !';
                            setTimeout(function() {
                                btn.textContent = originalText;
                            }, 2000);
                        } else {
                            alert('Impossible de copier automatiquement');
                        }
                    } catch (err) {
                        alert('Impossible de copier automatiquement');
                    }
                }
            }
        }
    });
    
    // *** GESTIONNAIRE TÉLÉCHARGER SQL ***
    document.addEventListener('click', function(e) {
        if (e.target && e.target.id === 'download-sql-btn') {
            console.log('=== CLIC TÉLÉCHARGER SQL ===');
            var sqlTextarea = document.getElementById('sql-code');
            if (sqlTextarea) {
                var sqlText = sqlTextarea.value;
                try {
                    var blob = new Blob([sqlText], {type: 'text/plain'});
                    var url = window.URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = 'batch_update_collaborate_' + new Date().getTime() + '.sql';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(url);
                    console.log('Téléchargement initié');
                } catch (err) {
                    console.error('Erreur téléchargement:', err);
                    alert('Erreur lors du téléchargement');
                }
            }
        }
    });
    
    console.log('=== INITIALISATION TERMINÉE ===');
}

console.log('=== FICHIER JAVASCRIPT CHARGÉ COMPLÈTEMENT (SANS JQUERY) ===');