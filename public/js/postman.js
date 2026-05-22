const output = document.getElementById('output');
const workspaceList = document.getElementById('workspaceList');

function showMessage(msg, type = 'secondary') {
    output.className = `mt-4 alert alert-${type}`;
    output.textContent = msg;
    output.classList.remove('d-none');
}

// ➕ Créer un workspace
async function createWorkspace() {
    const name = document.getElementById('workspaceName').value.trim();
    const description = document.getElementById('workspaceDesc').value.trim();
    if (!name) return showMessage("Le nom du workspace est obligatoire", "warning");

    const res = await fetch('?action=createWorkspace', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({name, description})
    });
    const data = await res.json();
    // Ex : Workspace créé : {"workspace":{"id":"cbb9de1b-bea5-44ac-850e-d3191d5d647f","name":"Domaine Supply Chain Management"}}
    showMessage('Workspace créé : ' + JSON.stringify(data), 'success');

    // Création par défaut des 4 environnements
    ['dev', 'local', 'pre-prod', 'prod', 'recette'].forEach(envName => {
        const envValue = envName === 'pre-prod' ? "pp" : envName;
        createEnvironment(envName, "{\"environment\":\"" + envValue + "\"}", data.workspace.id);
    })

    loadWorkspaces();
}

async function beforeCreateEnvironment() {
    const name = document.getElementById('envName').value.trim();
    const workspaceId = document.getElementById('workspaceIdEnv').value.trim();
    const vars = document.getElementById('envVars').value.trim();
    const data = createEnvironment(name, vars, workspaceId);
    showMessage('Environnement créé : ' + JSON.stringify(data), 'success');
}

// 🌍 Créer un environnement
async function createEnvironment(name, vars, workspaceId) {
    if (!name || !vars) return showMessage("Nom et variables obligatoires", "warning");

    let variables;
    try {
        variables = JSON.parse(vars);
    } catch (e) {
        return showMessage("Format JSON invalide", "danger");
    }

    const res = await fetch('?action=createEnvironment', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({name, variables, workspaceId})
    });

    return await res.json();
}

// 📦 Import OpenAPI
async function importOpenApi() {
    const nameCollectionInput = document.getElementById('titleOpenApi');
    const nameCollection = nameCollectionInput.value.trim();
    const fileInput = document.getElementById('openapiFile');
    const workspaceId = document.getElementById('workspaceIdOpenApi').value.trim();
    let content = '';
    if (fileInput.files.length) {
        //return showMessage("Aucun fichier sélectionné", "warning");
        console.log(fileInput);
        const file = fileInput.files[0];
        content = await file.text();
    }
    else {
        content = document.getElementById('filePreview').innerHTML;
    }
    //console.log("content", content);

    try{
        const obj = loadYaml(content);
        //console.log(obj);
    
        if(!nameCollection) {
            nameCollection = obj.info.title + ' ' + obj.info.version;
            nameCollectionInput.value = nameCollection;
        }
        obj.info.title = nameCollection;

        const res = await fetch('?action=importOpenApi', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({fileContent: obj, workspaceId, nameCollection})
        });
        const data = await res.json();
        showMessage('Import OpenAPI effectué : ' + JSON.stringify(data), 'success');
        loadWorkspaces();
    
    } catch (e) {
        console.log(e);
        console.log(e.parsedLine, e.snippet);
        showMessage("Format YAML invalide", "danger");
    }
}

function sanitizeYaml(content) {
  return content
    // remplace <br> par retour ligne
    .replace(/<br\s*\/?>/gi, '\n')
    // sécurise les descriptions non encadrées
    // .replace(
    //   /(description:\s*)([^"\n][^\n]*)/g,
    //   '$1"$2"'
    // )
    .replace(
      /(description:\s*)([^|\n][^\n]*)/g,
      '$1"$2"'
    )
    ;
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

function repareYamlFile(content) {
    // Fallback : remplacer descriptions non quotées (best effort)
  const safeContent = content.replace(
    /(description:\s*)([^"\n][^\n]*)/g,
    '$1"$2"'
  );

  const doc = jsyaml.load(safeContent);
}

async function getWorkspaces() {
    const res = await fetch('?action=getWorkspaces');
    const data = await res.json();
    return data.workspaces;
}

// 📋 Charger tous les workspaces et leurs données associées
async function loadWorkspaces() {
    workspaceList.innerHTML = '<p>Chargement en cours...</p>';
    const workspaces = await getWorkspaces();

    if (!workspaces) {
        workspaceList.innerHTML = '<p class="text-danger">Erreur de récupération des workspaces</p>';
        return;
    }

    workspaceList.innerHTML = workspaces.map(ws => `
    <div class="card mb-3 shadow-sm">
      <div class="card-body">
        <h5>${ws.name}</h5>
        <p class="text-muted">${ws.type}</p>
        <button class="btn btn-sm btn-outline-info" onclick="showWorkspaceDetails('${ws.id}', this)">Afficher détails</button>
        <div id="details-${ws.id}" class="mt-3" style="display:none;"></div>
      </div>
    </div>
  `).join('');
}

// 🔍 Détails d’un workspace
async function showWorkspaceDetails(id, btn) {
    const detailsDiv = document.getElementById(`details-${id}`);
    const isVisible = detailsDiv.style.display === 'block';

    if (isVisible) {
        detailsDiv.style.display = 'none';
        btn.textContent = 'Afficher détails';
        return;
    }

    btn.textContent = 'Masquer détails';
    detailsDiv.innerHTML = '<p>Chargement...</p>';
    detailsDiv.style.display = 'block';

    const res = await fetch(`?action=getWorkspaceDetails&id=${id}`);
    const data = await res.json();

    const envList = data.workspace.environments?.map(e => `<li>${e.name}</li>`).join('') || '<li>Aucun environnement</li>';
    const collList = data.workspace.collections?.map(c => `<li>${c.name}</li>`).join('') || '<li>Aucune collection</li>';

    detailsDiv.innerHTML = `
    <h6>Environnements :</h6>
    <ul>${envList}</ul>
    <h6>Collections :</h6>
    <ul>${collList}</ul>
  `;
}

async function initWorkspacesList() {
    const workspaces = await getWorkspaces();
    const workspacesHtml = workspaces.map(ws => `
    <option value="${ws.id}">${ws.name}</option>
  `).join('');

    ["workspaceIdEnv", "workspaceIdOpenApi"].forEach(id => {
        const list1 = document.getElementById(id);
        list1.innerHTML = `<option value="">Sélectionnez un workspace (optionnel)</option>` + workspacesHtml;
    })
}

function onChangeFilePreview(event) {
    const file = event.target.files[0];
    const title = document.getElementById('titleOpenApi');
    title.innerHTML = ''; // vide l'aperçu précédent
    // vide l'aperçu précédent
    setPreview('');

    if (!file) {
        return;
    }

    // Vérifie la taille si besoin
    if (file.size > 5 * 1024 * 1024) { // 5 Mo max
        setPreview('⚠️ Fichier trop volumineux pour un aperçu.')
        return;
    }

    const reader = new FileReader();
    reader.onload = function (e) {
        // Affiche le contenu brut (texte YAML, JSON, etc.)
        let content = e.target.result;

        // Si c’est du JSON, on peut le formater joliment
        if (file.name.endsWith('.json')) {
            try {
                content = JSON.stringify(JSON.parse(content), null, 2);
                setPreview(content);
            } catch (err) {
                console.warn('Erreur de parsing JSON:', err);
            }
        }
        if (file.name.endsWith('.yaml')) {
            try {
                setPreview(content);
                // Parse YAML
                //const openapi = jsyaml.load(content);
                //title.innerHTML = openapi.servers.url.replace("https://", "").replace(".{environment}.mdm-int.net", "") + " - " + openapi.info.version;
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
    preview.innerHTML = ''; // vide l'aperçu précédent
    preview.innerHTML = content;
}

function loadTitleOpenApi(content) {
    try {
        // Parse YAML
        const openapi = jsyaml.load(content);

        // Récupère info.title
        if (openapi?.info?.title) {
            document.getElementById('titleOpenApi').value = openapi.info.title + ' ' + openapi.info.version;
        }
    } catch (err) {
        console.error('Erreur parsing YAML', err);
        alert('Impossible de lire le fichier OpenAPI');
    }
}

function renderBreadcrumbs(path) {
    const bc = document.getElementById('breadcrumbs');
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
    const search = document.getElementById('treeSearch').value.toLowerCase();
    const tree = document.getElementById('repoTree');
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
    // Efface le filtre sinon rien ne s'affiche
    document.getElementById('treeSearch').value = '';
    fetch(`?action=tree&path=${encodeURIComponent(path)}`)
        .then(res => res.json())
        .then(data => {
            const tree = document.getElementById('repoTree');
            tree.innerHTML = '';
            currentTree = data;
            renderBreadcrumbs(path);
            renderTree(data);
        });
}

function selectOpenApi(path) {
    fetch(`?action=file&file=${encodeURIComponent(path)}`)
        .then(res => res.text())
        .then(content => {
            const json = JSON.parse(content);
            window.openapiContent = json.content;
            loadTitleOpenApi(json.content);
            setPreview(json.content)
        });
}