// ===== VARIABLES =====
let allMovies = [];
let selectedMovie = null;
let showingFavorites = false;
let showingWatchlist = false;


// ===== LOCAL STORAGE HELPERS =====
const getData = key => JSON.parse(localStorage.getItem(key) || "[]");
const saveData = (key, data) => localStorage.setItem(key, JSON.stringify(data));

const getFavorites = () => getData("favorites");
const getWatchlist = () => getData("watchlist");


// ===== FAVORITES =====
function toggleFavorite(title) {
    let fav = getFavorites();

    fav.includes(title)
        ? fav.splice(fav.indexOf(title), 1)
        : fav.push(title);

    saveData("favorites", fav);
    displayMovies(showingFavorites ? getFavoriteMovies() : allMovies);
}

const isFavorited = title => getFavorites().includes(title);


// ===== WATCHLIST =====
function toggleWatchlist(title) {
    let watch = getWatchlist();

    watch.includes(title)
        ? watch.splice(watch.indexOf(title), 1)
        : watch.push(title);

    saveData("watchlist", watch);

    if (selectedMovie) showMovieDetails(selectedMovie);
}

const isInWatchlist = title => getWatchlist().includes(title);


// ===== FILTER MOVIES =====
const getFavoriteMovies = () =>
    allMovies.filter(m => getFavorites().includes(m.title));

const getWatchlistMovies = () =>
    allMovies.filter(m => getWatchlist().includes(m.title));


// ===== BUTTON TOGGLES =====
function toggleShowFavorites() {
    showingFavorites = !showingFavorites;

    const btn = document.getElementById("favBtn");

    if (showingFavorites) {
        btn.textContent = "Show All Movies";
        displayMovies(getFavoriteMovies());
    } else {
        btn.textContent = `Show Favorites (${getFavorites().length})`;
        displayMovies(allMovies);
    }
}

function toggleShowWatchlist() {
    showingWatchlist = !showingWatchlist;

    const btn = document.getElementById("watchBtn");

    if (showingWatchlist) {
        btn.textContent = "Show All Movies";
        displayMovies(getWatchlistMovies());
    } else {
        btn.textContent = `Show Watchlist (${getWatchlist().length})`;
        displayMovies(allMovies);
    }
}


// ===== MOVIE MODAL =====
function showMovieDetails(movie) {
    selectedMovie = movie;

    document.getElementById("modalTitle").textContent = movie.title;
    document.getElementById("modalDate").textContent =
        "Release: " + (movie.release_date || "N/A");

    document.getElementById("modalOverview").textContent =
        movie.overview || "No description available";

    const poster = movie.poster_path
        ? "https://image.tmdb.org/t/p/w500" + movie.poster_path
        : "https://via.placeholder.com/500x750";

    document.getElementById("modalPoster").src = poster;

    const watchBtn = document.getElementById("watchlistBtn");

    if (isInWatchlist(movie.title)) {
        watchBtn.textContent = "✓ In Watchlist";
        watchBtn.style.background = "#e50914";
    } else {
        watchBtn.textContent = "+ Add to Watchlist";
        watchBtn.style.background = "#555";
    }

    document.getElementById("movieModal").style.display = "block";
}


// ===== CLOSE MODAL =====
function closeModal() {
    document.getElementById("movieModal").style.display = "none";
    selectedMovie = null;
}


// ===== LOAD MOVIES =====
async function loadMovies() {

    const container = document.getElementById("movieContainer");
    container.innerHTML = '<div class="status-box">Loading movies...</div>';

    try {
        const res = await fetch("movies.php");
        const data = await res.json();

        if (!data.success) throw "error";

        allMovies = data.movies || [];
        displayMovies(allMovies);

    } catch {
        container.innerHTML = '<div class="status-box">Network error</div>';
    }
}


// ===== DISPLAY MOVIES =====
function displayMovies(list) {

    const container = document.getElementById("movieContainer");

    if (!list.length) {
        container.innerHTML = '<div class="status-box">No movies</div>';
        return;
    }

    container.innerHTML = "";

    list.forEach(movie => {

        const poster = movie.poster_path
            ? "https://image.tmdb.org/t/p/w500" + movie.poster_path
            : "https://via.placeholder.com/500x750";

        const title = movie.title || "Untitled";
        const date = movie.release_date || "N/A";

        const star = isFavorited(title) ? "⭐" : "☆";

        const card = document.createElement("div");
        card.className = "movie-card";

        card.innerHTML = `
        <div style="position:relative;">
            <img class="movie-poster" src="${poster}">
            <button class="fav-btn">${star}</button>
        </div>

        <div class="movie-info">
            <div class="movie-title">${title}</div>
            <div class="movie-date">Release: ${date}</div>
        </div>
        `;

        card.querySelector(".fav-btn").onclick = e => {
            e.stopPropagation();
            toggleFavorite(title);
        };

        card.onclick = () => showMovieDetails(movie);

        container.appendChild(card);
    });
}


// ===== SEARCH =====
function searchMovies() {

    const q = document
        .getElementById("searchInput")
        .value
        .toLowerCase();

    const filtered = allMovies.filter(m =>
        m.title && m.title.toLowerCase().includes(q)
    );

    displayMovies(filtered);
}


// ===== PROFILE DROPDOWN =====
document
.getElementById("profileButton")
.onclick = () =>
document
.getElementById("profileDropdown")
.classList.toggle("show");


// ===== START =====
loadMovies();