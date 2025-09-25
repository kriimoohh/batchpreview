// Fichier: amd/src/preview.js - Version corrigée

define(['jquery'], function($) {
    return {
        init: function() {
            // Animation des éléments au chargement
            $('.mform fieldset').hide().fadeIn(800);
        },
        
        initPreview: function() {
            // Copier le SQL dans le presse-papiers
            $('#copy-sql-btn').on('click', function() {
                var sqlText = $('#sql-code').val();
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(sqlText).then(function() {
                        // Feedback visuel
                        var btn = $('#copy-sql-btn');
                        var originalText = btn.text();
                        btn.text('Copié !').addClass('copied');
                        
                        setTimeout(function() {
                            btn.text(originalText).removeClass('copied');
                        }, 2000);
                    }).catch(function(err) {
                        console.error('Erreur lors de la copie:', err);
                        alert('Erreur lors de la copie dans le presse-papiers');
                    });
                } else {
                    // Fallback pour navigateurs anciens
                    $('#sql-code').select();
                    try {
                        document.execCommand('copy');
                        var btn = $('#copy-sql-btn');
                        var originalText = btn.text();
                        btn.text('Copié !').addClass('copied');
                        setTimeout(function() {
                            btn.text(originalText).removeClass('copied');
                        }, 2000);
                    } catch (err) {
                        alert('Impossible de copier automatiquement. Veuillez sélectionner et copier manuellement.');
                    }
                }
            });
            
            // Télécharger le SQL
            $('#download-sql-btn').on('click', function() {
                var sqlText = $('#sql-code').val();
                var blob = new Blob([sqlText], {type: 'text/plain'});
                var url = window.URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = 'batch_update_collaborate_' + new Date().getTime() + '.sql';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
            });
            
            // Animation des lignes du tableau
            $('.preview-table tr').each(function(index) {
                $(this).css('opacity', 0).delay(index * 100).animate({opacity: 1}, 500);
            });
            
            // CORRECTION: Gestionnaire amélioré pour le bouton "Appliquer les modifications"
            $(document).on('click', '#apply-changes-btn', function(e) {
                e.preventDefault();
                
                console.log('=== DEBUT APPLICATION MODIFICATIONS ===');
                
                var btn = $(this);
                var categoryid = btn.data('categoryid');
                var systemType = btn.data('system-type');
                
                console.log('Données bouton:', {
                    categoryid: categoryid, 
                    systemType: systemType,
                    btnLength: btn.length
                });
                
                // Vérifier que les données sont présentes
                if (!categoryid || !systemType) {
                    alert('Erreur: Données manquantes\n- categoryid: ' + categoryid + '\n- systemType: ' + systemType);
                    return;
                }
                
                // Collecter toutes les modifications depuis les inputs éditables
                var changes = [];
                var newNameInputs = $('.new-name-input');
                var currentNameInputs = $('.current-name-input');
                
                console.log('Elements trouvés:', {
                    newNameInputs: newNameInputs.length,
                    currentNameInputs: currentNameInputs.length
                });
                
                if (newNameInputs.length === 0) {
                    alert('Erreur: Aucun champ de saisie trouvé sur la page.\nVérifiez que le tableau est bien chargé.');
                    return;
                }
                
                newNameInputs.each(function() {
                    var input = $(this);
                    var roomId = input.data('room-id');
                    var newName = input.val().trim();
                    
                    // Trouver le nom actuel correspondant
                    var currentNameInput = $('.current-name-input[data-room-id="' + roomId + '"]');
                    var currentName = currentNameInput.length > 0 ? currentNameInput.val().trim() : '';
                    
                    console.log('Traitement room:', {
                        roomId: roomId, 
                        currentName: currentName, 
                        newName: newName
                    });
                    
                    if (roomId && newName && newName !== currentName) {
                        changes.push({
                            room_id: parseInt(roomId),
                            new_name: newName,
                            current_name: currentName
                        });
                    }
                });
                
                console.log('Modifications collectées:', changes);
                
                if (changes.length === 0) {
                    alert('Aucune modification détectée.\n\nVérifiez que :\n- Vous avez modifié au moins un nom\n- Les nouveaux noms sont différents des actuels');
                    return;
                }
                
                // Confirmation avec détails
                var confirmMsg = 'Êtes-vous sûr de vouloir appliquer ' + changes.length + ' modification(s) ?\n\n';
                confirmMsg += 'Aperçu des modifications :\n';
                for (var i = 0; i < Math.min(changes.length, 5); i++) {
                    confirmMsg += '• Salle ' + changes[i].room_id + ':\n';
                    confirmMsg += '  "' + changes[i].current_name + '"\n';
                    confirmMsg += '  → "' + changes[i].new_name + '"\n\n';
                }
                if (changes.length > 5) {
                    confirmMsg += '... et ' + (changes.length - 5) + ' autres modifications\n\n';
                }
                confirmMsg += '⚠️ Cette action modifiera directement la base de données et est irréversible.';
                
                if (!confirm(confirmMsg)) {
                    return;
                }
                
                // Désactiver le bouton et afficher le loading
                btn.prop('disabled', true)
                   .html('<i class="fa fa-spinner fa-spin"></i> Application en cours...')
                   .addClass('btn-loading');
                
                // CORRECTION: Construction d'URL plus robuste
                var ajaxUrl;
                try {
                    if (typeof M !== 'undefined' && M.cfg && M.cfg.wwwroot) {
                        ajaxUrl = M.cfg.wwwroot + '/local/batchpreview/apply.php';
                    } else {
                        // Fallback - construire depuis l'URL actuelle
                        var currentPath = window.location.pathname;
                        var basePath = currentPath.substring(0, currentPath.indexOf('/local/batchpreview/')) + '/local/batchpreview/';
                        ajaxUrl = window.location.protocol + '//' + window.location.host + basePath + 'apply.php';
                    }
                } catch (urlError) {
                    console.error('Erreur construction URL:', urlError);
                    ajaxUrl = '/local/batchpreview/apply.php'; // URL relative en dernier recours
                }
                
                console.log('URL AJAX finale:', ajaxUrl);
                
                var requestData = {
                    categoryid: parseInt(categoryid),
                    system_type: systemType,
                    changes: changes
                };
                
                console.log('Données de la requête:', requestData);
                
                // Envoyer la requête AJAX
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    contentType: 'application/json; charset=utf-8',
                    data: JSON.stringify(requestData),
                    timeout: 45000, // 45 secondes de timeout
                    beforeSend: function(xhr) {
                        // Ajouter des headers de sécurité si nécessaire
                        console.log('Envoi de la requête AJAX...');
                    },
                    success: function(response, textStatus, xhr) {
                        console.log('Réponse reçue:', {
                            status: xhr.status,
                            response: response,
                            textStatus: textStatus
                        });
                        
                        if (response && response.success) {
                            var message = '✅ Succès !\n\n';
                            message += response.message || (response.updated_count + ' modifications appliquées');
                            if (response.updated_count) {
                                message += '\n\nNombre de modifications : ' + response.updated_count;
                            }
                            alert(message);
                            
                            // Actualiser la page après un court délai
                            setTimeout(function() {
                                window.location.reload();
                            }, 2000);
                        } else {
                            var errorMsg = '❌ Erreurs rencontrées :\n\n';
                            if (response && response.errors && Array.isArray(response.errors)) {
                                errorMsg += response.errors.join('\n');
                            } else if (response && response.error) {
                                errorMsg += response.error;
                            } else {
                                errorMsg += 'Erreur inconnue. Consultez la console développeur.';
                            }
                            alert(errorMsg);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Erreur AJAX complète:', {
                            xhr: xhr,
                            status: status,
                            error: error,
                            responseText: xhr.responseText,
                            readyState: xhr.readyState
                        });
                        
                        var errorMsg = '❌ Erreur de communication :\n\n';
                        
                        if (xhr.status === 0) {
                            errorMsg += 'Impossible de contacter le serveur.\nVérifiez votre connexion internet et l\'URL du serveur.';
                        } else if (xhr.status === 404) {
                            errorMsg += 'Page non trouvée (404).\nLe fichier apply.php est introuvable à l\'adresse :\n' + ajaxUrl;
                        } else if (xhr.status === 403) {
                            errorMsg += 'Accès refusé (403).\nVérifiez vos permissions d\'administrateur.';
                        } else if (xhr.status === 500) {
                            errorMsg += 'Erreur serveur interne (500).\nConsultez les logs du serveur.';
                        } else {
                            errorMsg += 'Code d\'erreur HTTP : ' + xhr.status + '\n';
                            errorMsg += 'Status : ' + status + '\n';
                            errorMsg += 'Erreur : ' + error;
                        }
                        
                        if (xhr.responseJSON && xhr.responseJSON.error) {
                            errorMsg += '\n\nDétail du serveur :\n' + xhr.responseJSON.error;
                        } else if (xhr.responseText && xhr.responseText.length < 500) {
                            errorMsg += '\n\nRéponse serveur :\n' + xhr.responseText;
                        }
                        
                        errorMsg += '\n\n--- Informations de debug ---';
                        errorMsg += '\nURL utilisée : ' + ajaxUrl;
                        errorMsg += '\nNombre de modifications : ' + changes.length;
                        
                        alert(errorMsg);
                    },
                    complete: function(xhr, status) {
                        console.log('Requête AJAX terminée:', {status: status, readyState: xhr.readyState});
                        
                        // Réactiver le bouton dans tous les cas
                        setTimeout(function() {
                            btn.prop('disabled', false)
                               .html('<i class="fa fa-check"></i> Appliquer les modifications')
                               .removeClass('btn-loading');
                        }, 1000);
                    }
                });
            });
            
            // Debug : Vérifier la présence des éléments au chargement
            setTimeout(function() {
                console.log('=== VERIFICATION ELEMENTS PAGE ===');
                console.log('- Bouton apply trouvé:', $('#apply-changes-btn').length);
                console.log('- Inputs new-name trouvés:', $('.new-name-input').length);
                console.log('- Inputs current-name trouvés:', $('.current-name-input').length);
                console.log('- Tableau preview trouvé:', $('#preview-table').length);
                
                if ($('#apply-changes-btn').length === 0) {
                    console.warn('ATTENTION: Bouton #apply-changes-btn non trouvé !');
                }
                if ($('.new-name-input').length === 0) {
                    console.warn('ATTENTION: Aucun input .new-name-input trouvé !');
                }
            }, 1000);
        }
    };
});