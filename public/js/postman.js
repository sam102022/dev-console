const output = document.getElementById('output');
const workspaceList = document.getElementById('workspaceList');

// 💡 Helper pour gérer les états de chargement des boutons (avec spinners)
function setLoading(btn, isLoading, loadingText = "Chargement...") {
    if (!btn) return;
    if (isLoading) {
        btn.disabled = true;
        btn.dataset.originalHtml = btn.innerHTML;
        btn.innerHTML = `<span class="spinner-border spinner-border-sm mr-1" role="status" aria-hidden="true"></span> ${loadingText}`;
    } else {
        btn.disabled = false;
        if (btn.dataset.originalHtml !== undefined) {
            btn.innerHTML = btn.dataset.originalHtml;
        }
    }
}

// 📢 Afficher des messages formatés élégamment
function showMessage(msg, type = 'secondary') {
    output.className = `mt-4 alert alert-${type} alert-dismissible fade show`;
    output.innerHTML = `
        <i class="fas ${type === 'success' ? 'fa-check-circle' : (type === 'danger' ? 'fa-exclamation-triangle' : 'fa-info-circle')} mr-2"></i>
        <span>${msg}</span>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    `;
    output.classList.remove('d-none');
}

// ➕ Créer un workspace
async function createWorkspace(btn) {
    const name = document.getElementById('workspaceName').value.trim();
    const description = document.getElementById('workspaceDesc').value.trim();
    if (!name) return showMessage("Le nom du workspace est obligatoire", "warning");

    setLoading(btn, true, "Création du workspace...");

    try {
        const res = await fetch('?action=createWorkspace', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({name, description})
        });

        if (!res.ok) {
            throw new Error(`Erreur HTTP: ${res.status}`);
        }

        const data = await res.json();
        
        if (data.error) {
            throw new Error(data.error);
        }

        const wsName = data.workspace?.name || name;
        const wsId = data.workspace?.id || "N/A";
        showMessage(`Le workspace <strong>${wsName}</strong> a été créé avec succès (ID: ${wsId}).`, 'success');

        // Création par défaut des 5 environnements
        const envNames = ['dev', 'local', 'pre-prod', 'prod', 'recette'];
        for (const envName of envNames) {
            const envValue = envName === 'pre-prod' ? "pp" : envName;
            await createEnvironment(envName, JSON.stringify({ environment: envValue }), data.workspace.id);
        }

        // Réinitialiser les champs de saisie
        document.getElementById('workspaceName').value = '';
        document.getElementById('workspaceDesc').value = '';

        // Mettre à jour la liste des workspaces ET les menus déroulants de sélection
        await initWorkspacesList();
        await loadWorkspaces();

    } catch (error) {
        console.error(error);
        showMessage(`Échec de la création du workspace : ${error.message}`, 'danger');
    } finally {
        setLoading(btn, false);
    }
}

// 🌍 Gérer la soumission du formulaire d'environnement
async function beforeCreateEnvironment(btn) {
    const name = document.getElementById('envName').value.trim();
    const workspaceId = document.getElementById('workspaceIdEnv').value.trim();
    const vars = document.getElementById('envVars').value.trim();

    if (!name || !vars) return showMessage("Nom et variables obligatoires", "warning");

    setLoading(btn, true, "Création de l'environnement...");

    try {
        // Correction de bug : "await" manquant pour attendre le retour de la requête
        const data = await createEnvironment(name, vars, workspaceId);
        
        if (data.error) {
            throw new Error(data.error);
        }

        showMessage(`L'environnement <strong>${name}</strong> a été configuré avec succès !`, 'success');
        
        // Réinitialiser le formulaire
        document.getElementById('envName').value = '';
        document.getElementById('envVars').value = '';
        document.getElementById('workspaceIdEnv').value = '';

        await loadWorkspaces();

    } catch (error) {
        console.error(error);
        showMessage(`Échec de la création de l'environnement : ${error.message}`, 'danger');
    } finally {
        setLoading(btn, false);
    }
}

// 🌍 Créer un environnement (appel API sous-jacent)
async function createEnvironment(name, vars, workspaceId) {
    let variables;
    try {
        variables = JSON.parse(vars);
    } catch (e) {
        throw new Error("Format JSON invalide");
    }

    const res = await fetch('?action=createEnvironment', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({name, variables, workspaceId})
    });

    if (!res.ok) {
        throw new Error(`Erreur HTTP: ${res.status}`);
    }

    return await res.json();
}

// 📦 Import OpenAPI
async function importOpenApi(btn) {
    const nameCollectionInput = document.getElementById('titleOpenApi');
    // Correction de bug : nameCollection doit être déclaré avec "let" pour pouvoir être réassigné
    let nameCollection = nameCollectionInput.value.trim();
    const fileInput = document.getElementById('openapiFile');
    const workspaceId = document.getElementById('workspaceIdOpenApi').value.trim();
    let content = '';
    
    if (fileInput.files.length) {
        const file = fileInput.files[0];
        content = await file.text();
    } else {
        content = document.getElementById('filePreview').textContent;
    }

    if (!content || content.trim() === '') {
        return showMessage("Veuillez sélectionner un fichier OpenAPI ou un contrat GitLab", "warning");
    }

    setLoading(btn, true, "Importation de l'OpenAPI...");

    try {
        const obj = loadYaml(content);
    
        if (!nameCollection) {
            nameCollection = (obj.info?.title || "Collection") + ' ' + (obj.info?.version || "1.0.0");
            nameCollectionInput.value = nameCollection;
        }
        
        if (obj.info) {
            obj.info.title = nameCollection;
        }

        const res = await fetch('?action=importOpenApi', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({fileContent: obj, workspaceId, nameCollection})
        });

        if (!res.ok) {
            throw new Error(`Erreur HTTP: ${res.status}`);
        }

        const data = await res.json();
        
        if (data.error) {
            throw new Error(data.error);
        }

        showMessage(`Le contrat OpenAPI <strong>${nameCollection}</strong> a été importé avec succès sous forme de collection !`, 'success');
        
        // Réinitialiser les champs d'import
        document.getElementById('titleOpenApi').value = '';
        fileInput.value = '';
        document.getElementById('workspaceIdOpenApi').value = '';
        setPreview('');

        await loadWorkspaces();
    
    } catch (error) {
        console.error(error);
        showMessage(`Échec de l'importation de l'OpenAPI : ${error.message}`, 'danger');
    } finally {
        setLoading(btn, false);
    }
}

function sanitizeYaml(content) {
  return content
    // remplace <br> par retour ligne
    .replace(/<br\s*\/?>/gi, '\n')
    // sécurise les descriptions non encadrées
    .replace(
      /(description:\s*)([^|\n][^\n]*)/g,
      '$1"$2"'
    );
}

function loadYaml(content) {
    try {
        // Utilisation de js-yaml au lieu de YAML.parse (yamljs)
        const doc = jsyaml.load(content);
        return doc;
    } catch (e) {
        console.warn('Parsing YAML échoué, tentative de fallback');

        // Fallback : remplacer descriptions non quotées (best effort)
        const safeContent = sanitizeYaml(content);
        const doc = jsyaml.load(safeContent);
        return doc;
    }
}

async function getWorkspaces() {
    try {
        const res = await fetch('?action=getWorkspaces');
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();
        return data.workspaces;
    } catch (e) {
        console.error("Impossible de récupérer les workspaces", e);
        return null;
    }
}

// 📋 Charger tous les workspaces et leurs données associées
async function loadWorkspaces(btn) {
    setLoading(btn, true, "Chargement...");
    workspaceList.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-secondary" role="status">
                <span class="sr-only">Chargement en cours...</span>
            </div>
            <p class="text-muted mt-2 mb-0">Récupération des workspaces depuis Postman...</p>
        </div>
    `;
    
    try {
        const workspaces = await getWorkspaces();

        if (!workspaces || workspaces.length === 0) {
            workspaceList.innerHTML = `
                <div class="alert alert-info">
                    <i class="fas fa-info-circle mr-2"></i> Aucun workspace Postman n'a été trouvé.
                </div>
            `;
            return;
        }

        workspaceList.innerHTML = workspaces.map(ws => `
            <div class="card mb-3 shadow-sm border-left-info">
              <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-1 font-weight-bold text-dark">${ws.name}</h5>
                    <span class="badge badge-secondary text-capitalize">${ws.type}</span>
                </div>
                <div>
                    <button class="btn btn-sm btn-outline-primary" onclick="showWorkspaceDetails('${ws.id}', this)">
                        <i class="fas fa-eye mr-1"></i> Afficher détails
                    </button>
                </div>
              </div>
              <div id="details-${ws.id}" class="card-footer bg-light" style="display:none; border-top: 1px solid rgba(0,0,0,.125);"></div>
            </div>
        `).join('');

    } catch (error) {
        workspaceList.innerHTML = `<p class="text-danger p-3"><i class="fas fa-exclamation-circle mr-1"></i> Erreur lors du chargement : ${error.message}</p>`;
    } finally {
        setLoading(btn, false);
    }
}

// 🔍 Détails d’un workspace
async function showWorkspaceDetails(id, btn) {
    const detailsDiv = document.getElementById(`details-${id}`);
    const isVisible = detailsDiv.style.display === 'block';

    if (isVisible) {
        detailsDiv.style.display = 'none';
        btn.innerHTML = '<i class="fas fa-eye mr-1"></i> Afficher détails';
        return;
    }

    btn.innerHTML = '<span class="spinner-border spinner-border-sm mr-1" role="status" aria-hidden="true"></span> Chargement...';
    detailsDiv.innerHTML = '<p class="text-muted small m-0"><i class="fas fa-circle-notch fa-spin mr-1"></i> Chargement des environnements et des collections...</p>';
    detailsDiv.style.display = 'block';

    try {
        const res = await fetch(`?action=getWorkspaceDetails&id=${id}`);
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();

        if (data.error) {
            throw new Error(data.error);
        }

        const envList = data.workspace.environments?.map(e => `
            <li class="list-group-item d-flex align-items-center py-1 px-3 border-0 bg-transparent">
                <i class="fas fa-cloud-sun text-info mr-2 small"></i> ${e.name}
            </li>
        `).join('') || '<li class="list-group-item text-muted py-1 px-3 border-0 bg-transparent">Aucun environnement</li>';
        
        const collList = data.workspace.collections?.map(c => `
            <li class="list-group-item d-flex align-items-center py-1 px-3 border-0 bg-transparent">
                <i class="fas fa-folder-open text-warning mr-2 small"></i> ${c.name}
            </li>
        `).join('') || '<li class="list-group-item text-muted py-1 px-3 border-0 bg-transparent">Aucune collection</li>';

        detailsDiv.innerHTML = `
            <div class="row">
                <div class="col-md-6 border-right">
                    <h6 class="font-weight-bold text-secondary mb-2 small uppercase"><i class="fas fa-globe mr-1"></i> Environnements</h6>
                    <ul class="list-group list-group-flush small m-0">${envList}</ul>
                </div>
                <div class="col-md-6">
                    <h6 class="font-weight-bold text-secondary mb-2 small uppercase"><i class="fas fa-list mr-1"></i> Collections</h6>
                    <ul class="list-group list-group-flush small m-0">${collList}</ul>
                </div>
            </div>
        `;
        btn.innerHTML = '<i class="fas fa-eye-slash mr-1"></i> Masquer détails';

    } catch (error) {
        console.error(error);
        detailsDiv.innerHTML = `<span class="text-danger small"><i class="fas fa-exclamation-circle mr-1"></i> Impossible de charger les détails.</span>`;
        btn.innerHTML = '<i class="fas fa-eye mr-1"></i> Afficher détails';
    }
}

// 🔄 Synchroniser dynamiquement tous les sélecteurs de workspaces
async function initWorkspacesList() {
    const workspaces = await getWorkspaces();
    if (!workspaces) return;
    
    const workspacesHtml = workspaces.map(ws => `
        <option value="${ws.id}">${ws.name}</option>
    `).join('');

    ["workspaceIdEnv", "workspaceIdOpenApi"].forEach(id => {
        const list1 = document.getElementById(id);
        if (list1) {
            list1.innerHTML = `<option value="">Sélectionnez un workspace (optionnel)</option>` + workspacesHtml;
        }
    });
}

function onChangeFilePreview(event) {
    const file = event.target.files[0];
    const title = document.getElementById('titleOpenApi');
    title.value = ''; // vide l'aperçu précédent
    setPreview('');

    if (!file) {
        return;
    }

    if (file.size > 5 * 1024 * 1024) { // 5 Mo max
        setPreview('⚠️ Fichier trop volumineux pour un aperçu.');
        return;
    }

    const reader = new FileReader();
    reader.onload = function (e) {
        let content = e.target.result;

        if (file.name.endsWith('.json')) {
            try {
                content = JSON.stringify(JSON.parse(content), null, 2);
                setPreview(content);
            } catch (err) {
                console.warn('Erreur de parsing JSON:', err);
            }
        }
        if (file.name.endsWith('.yaml') || file.name.endsWith('.yml')) {
            try {
                setPreview(content);
            } catch (err) {
                console.warn('Erreur de parsing YAML:', err);
            }
        }
    };
    reader.readAsText(file);
}

function onChangeFileTitle(event) {
    const file = event.target.files[0];
    if (!file) return;

    const reader = new FileReader();

    reader.onload = function (e) {
        try {
            const content = e.target.result;
            loadTitleOpenApi(content);

        } catch (err) {
            console.error('Erreur parsing YAML', err);
            alert('Impossible de lire le fichier OpenAPI');
        }
    };

    reader.readAsText(file);
}

function setPreview(content) {
    const preview = document.getElementById('filePreview');
    if (preview) {
        preview.textContent = content; // utiliser textContent pour préserver le formatage brut sans échapper d'HTML
    }
}

function loadTitleOpenApi(content) {
    try {
        const openapi = jsyaml.load(content);
        if (openapi?.info?.title) {
            const version = openapi.info.version ? ' ' + openapi.info.version : '';
            document.getElementById('titleOpenApi').value = openapi.info.title + version;
        }
    } catch (err) {
        console.error('Erreur parsing YAML', err);
    }
}

function renderBreadcrumbs(path) {
    const bc = document.getElementById('breadcrumbs');
    if (!bc) return;
    bc.innerHTML = '';

    const parts = path ? path.split('/') : [];

    bc.innerHTML += `
      <li class="breadcrumb-item">
        <a href="#" onclick="loadTree('')">🏠 Root</a>
      </li>`;

    let acc = '';
    parts.forEach((p, i) => {
        acc += (i ? '/' : '') + p;
        bc.innerHTML += `
          <li class="breadcrumb-item">
            <a href="#" onclick="loadTree('${acc}')">${p}</a>
          </li>`;
    });
}

function renderTree(data) {
    const searchInput = document.getElementById('treeSearch');
    if (!searchInput) return;
    const search = searchInput.value.toLowerCase();
    const tree = document.getElementById('repoTree');
    if (!tree) return;
    tree.innerHTML = '';

    data
      .filter(item => item.name.toLowerCase().includes(search))
      .forEach(item => {
        if (item.type === 'tree') {
            tree.innerHTML += `
              <li class="list-group-item"
                  onclick="loadTree('${item.path}')">
                  📁 ${item.name}
              </li>`;
        } else {
            const isOpenApi = item.name.match(/openapi.*\.ya?ml$/i);
            tree.innerHTML += `
              <li class="list-group-item ${isOpenApi ? 'repo-file-openapi' : ''}"
                  onclick="${isOpenApi ? `selectOpenApi('${item.path}')` : ''}">
                  📄 ${item.name}
              </li>`;
        }
      });
}

function loadTree(path = '') {
    const searchInput = document.getElementById('treeSearch');
    if (searchInput) {
        searchInput.value = '';
    }
    
    fetch(`?action=tree&path=${encodeURIComponent(path)}`)
        .then(res => res.json())
        .then(data => {
            currentTree = data;
            renderBreadcrumbs(path);
            renderTree(data);
        })
        .catch(err => {
            console.error("Impossible de récupérer l'arbre de contrats", err);
        });
}

function selectOpenApi(path) {
    fetch(`?action=file&file=${encodeURIComponent(path)}`)
        .then(res => res.json())
        .then(json => {
            window.openapiContent = json.content;
            loadTitleOpenApi(json.content);
            setPreview(json.content);
        })
        .catch(err => {
            console.error("Impossible de récupérer le contrat OpenAPI", err);
        });
}
