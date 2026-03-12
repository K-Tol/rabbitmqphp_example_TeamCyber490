const API = "api";

let movies = [];
let selectedMovie = null;
let showFavoritesOnly = false;
let showWatchlistOnly = false;
let watchlistSet = new Set();
const favoriteSet = new Set(JSON.parse(localStorage.getItem("favoriteMovies") || "[]"));

function userId() {
    const value = Number.parseInt(document.getElementById("userIdInput")?.value || "1", 10);
    return Number.isFinite(value) && value > 0 ? value : 1;
}

async function request(url, options = {}) {
    const res = await fetch(url, {
        headers: { "Content-Type": "application/json" },
        ...options,
    });

    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        throw new Error(data.error || "Request failed.");
    }

    return data;
}

function htmlSafe(text) {
    return String(text || "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}

async function loadMovies(search = "") {
    const container = document.getElementById("movieContainer");
    container.innerHTML = '<div class="status-box">Loading movies...</div>';

    const query = new URLSearchParams({ page: "1", limit: "30", search });

    try {
        const data = await request(`${API}/movies.php?${query.toString()}`);
        movies = data.movies || [];
        await refreshWatchlist();
        renderMovies();
    } catch (error) {
        container.innerHTML = `<div class="status-box">${htmlSafe(error.message)}</div>`;
    }
}

function renderMovies() {
    const container = document.getElementById("movieContainer");
    let list = [...movies];

    if (showFavoritesOnly) {
        list = list.filter((movie) => favoriteSet.has(movie.id));
    }
    if (showWatchlistOnly) {
        list = list.filter((movie) => watchlistSet.has(movie.id));
    }

    if (list.length === 0) {
        container.innerHTML = '<div class="status-box">No movies found.</div>';
        return;
    }

    container.innerHTML = list
        .map((movie) => {
            const inFav = favoriteSet.has(movie.id);
            const inWatch = watchlistSet.has(movie.id);
            const poster = movie.poster_url || "";

            return `
                <div class="movie-card" onclick="openMovie(${movie.id})">
                    <img class="movie-poster" src="${htmlSafe(poster)}" alt="${htmlSafe(movie.title)}">
                    <div class="movie-info">
                        <div class="movie-title">${htmlSafe(movie.title)}</div>
                        <div class="movie-date">Release: ${htmlSafe(movie.release_date || "TBA")}</div>
                        <div class="movie-overview">${htmlSafe((movie.overview || "").slice(0, 140))}</div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;">
                            <button class="refresh-btn" onclick="event.stopPropagation();toggleFavorite(${movie.id})">${inFav ? "Unfavorite" : "Favorite"}</button>
                            <button class="refresh-btn" onclick="event.stopPropagation();toggleWatchlist(${movie.id})">${inWatch ? "Remove Watchlist" : "Add Watchlist"}</button>
                        </div>
                    </div>
                </div>
            `;
        })
        .join("");
}

function syncCounts() {
    document.getElementById("favBtn").textContent = `Show Favorites (${favoriteSet.size})`;
    document.getElementById("watchBtn").textContent = `Show Watchlist (${watchlistSet.size})`;
}

function toggleFavorite(movieId) {
    if (favoriteSet.has(movieId)) {
        favoriteSet.delete(movieId);
    } else {
        favoriteSet.add(movieId);
    }

    localStorage.setItem("favoriteMovies", JSON.stringify([...favoriteSet]));
    syncCounts();
    renderMovies();
}

function toggleShowFavorites() {
    showFavoritesOnly = !showFavoritesOnly;
    renderMovies();
}

function toggleShowWatchlist() {
    showWatchlistOnly = !showWatchlistOnly;
    renderMovies();
}

async function refreshWatchlist() {
    try {
        const data = await request(`${API}/watchlist.php?user_id=${userId()}`);
        watchlistSet = new Set((data.watchlist || []).map((item) => item.movie_id));
    } catch (_) {
        watchlistSet = new Set();
    }

    syncCounts();
}

async function toggleWatchlist(movieId = selectedMovie?.id) {
    if (!movieId) {
        return;
    }

    try {
        if (watchlistSet.has(movieId)) {
            await request(`${API}/watchlist.php`, {
                method: "DELETE",
                body: JSON.stringify({ user_id: userId(), movie_id: movieId }),
            });
        } else {
            await request(`${API}/watchlist.php`, {
                method: "POST",
                body: JSON.stringify({ user_id: userId(), movie_id: movieId }),
            });
        }

        await refreshWatchlist();
        renderMovies();

        if (selectedMovie?.id === movieId) {
            document.getElementById("watchlistBtn").textContent = watchlistSet.has(movieId)
                ? "- Remove from Watchlist"
                : "+ Add to Watchlist";
        }
    } catch (error) {
        alert(error.message);
    }
}

function searchMovies() {
    const q = document.getElementById("searchInput")?.value || "";
    loadMovies(q);
}

function closeModal() {
    document.getElementById("movieModal").style.display = "none";
}

function renderStars(rating = 0) {
    const container = document.getElementById("ratingStars");
    container.innerHTML = "";

    for (let i = 1; i <= 5; i += 1) {
        const btn = document.createElement("button");
        btn.type = "button";
        btn.textContent = "★";
        btn.className = `star-btn ${i <= rating ? "active" : ""}`;
        btn.onclick = () => {
            document.getElementById("ratingInput").value = String(i);
            renderStars(i);
        };
        container.appendChild(btn);
    }
}

function renderReviewList(reviews) {
    const node = document.getElementById("modalReviews");
    if (!reviews.length) {
        node.innerHTML = '<div class="review-item">No reviews yet.</div>';
        return;
    }

    node.innerHTML = reviews
        .map(
            (r) => `
                <div class="review-item">
                    <div><strong>${htmlSafe(r.display_name || "User")}</strong> rated ${r.rating}/5</div>
                    <div>${htmlSafe(r.review_text || "")}</div>
                    <div class="meta-text">${htmlSafe(r.created_at || "")}</div>
                </div>
            `
        )
        .join("");
}

function renderThreadList(threads) {
    const node = document.getElementById("threadList");
    document.getElementById("threadPosts").innerHTML = "";

    if (!threads.length) {
        node.innerHTML = '<div class="thread-item">No threads yet.</div>';
        return;
    }

    node.innerHTML = threads
        .map(
            (t) => `
                <div class="thread-item">
                    <div class="thread-title" onclick="openThread(${t.id})">${htmlSafe(t.title)}</div>
                    <div>${htmlSafe(t.content)}</div>
                    <div class="meta-text">By ${htmlSafe(t.display_name || "User")} | Posts: ${t.post_count}</div>
                </div>
            `
        )
        .join("");
}

async function openMovie(movieId) {
    try {
        const data = await request(`${API}/movies.php?id=${movieId}`);
        selectedMovie = data.movie;

        document.getElementById("modalPoster").src = selectedMovie.poster_url || "";
        document.getElementById("modalTitle").textContent = selectedMovie.title || "";
        document.getElementById("modalDate").textContent = `Release: ${selectedMovie.release_date || "TBA"}`;
        document.getElementById("modalOverview").textContent = selectedMovie.overview || "";

        document.getElementById("watchlistBtn").textContent = watchlistSet.has(selectedMovie.id)
            ? "- Remove from Watchlist"
            : "+ Add to Watchlist";

        document.getElementById("ratingInput").value = "0";
        document.getElementById("reviewInput").value = "";
        renderStars(0);
        renderReviewList(data.reviews || []);
        renderThreadList(data.threads || []);

        document.getElementById("movieModal").style.display = "block";
    } catch (error) {
        alert(error.message);
    }
}

async function submitRating() {
    if (!selectedMovie) {
        return;
    }

    const rating = Number.parseInt(document.getElementById("ratingInput").value || "0", 10);
    const review = document.getElementById("reviewInput").value || "";

    if (rating < 1 || rating > 5) {
        alert("Select a rating from 1 to 5.");
        return;
    }

    try {
        await request(`${API}/reviews.php`, {
            method: "POST",
            body: JSON.stringify({
                user_id: userId(),
                movie_id: selectedMovie.id,
                rating,
                review,
            }),
        });

        await openMovie(selectedMovie.id);
        await loadRecommendations();
    } catch (error) {
        alert(error.message);
    }
}

async function createThread() {
    if (!selectedMovie) {
        return;
    }

    const title = document.getElementById("threadTitleInput").value.trim();
    const content = document.getElementById("threadContentInput").value.trim();

    if (!title || !content) {
        alert("Thread title and content are required.");
        return;
    }

    try {
        await request(`${API}/forums.php`, {
            method: "POST",
            body: JSON.stringify({
                type: "thread",
                user_id: userId(),
                movie_id: selectedMovie.id,
                title,
                content,
            }),
        });

        document.getElementById("threadTitleInput").value = "";
        document.getElementById("threadContentInput").value = "";
        await openMovie(selectedMovie.id);
    } catch (error) {
        alert(error.message);
    }
}

async function openThread(threadId) {
    try {
        const data = await request(`${API}/forums.php?thread_id=${threadId}`);
        const posts = data.posts || [];

        const node = document.getElementById("threadPosts");
        node.innerHTML = `
            <h4 style="color:#e50914;margin-bottom:8px;">${htmlSafe(data.thread.title || "")}</h4>
            ${posts
                .map(
                    (p) => `
                        <div class="post-item">
                            <div>${htmlSafe(p.content)}</div>
                            <div class="meta-text">${htmlSafe(p.display_name || "User")} | ${htmlSafe(p.created_at || "")}</div>
                        </div>
                    `
                )
                .join("")}
            <textarea id="replyInput" placeholder="Write a reply..." style="width:100%;padding:10px;border-radius:5px;border:1px solid #555;background:#333;color:#fff;min-height:70px;margin-top:8px;"></textarea>
            <button class="refresh-btn" style="margin-top:8px;" onclick="replyToThread(${threadId})">Reply</button>
        `;
    } catch (error) {
        alert(error.message);
    }
}

async function replyToThread(threadId) {
    const content = (document.getElementById("replyInput")?.value || "").trim();
    if (!content) {
        alert("Reply cannot be empty.");
        return;
    }

    try {
        await request(`${API}/forums.php`, {
            method: "POST",
            body: JSON.stringify({
                type: "post",
                user_id: userId(),
                thread_id: threadId,
                content,
            }),
        });

        await openThread(threadId);
        if (selectedMovie) {
            await openMovie(selectedMovie.id);
        }
    } catch (error) {
        alert(error.message);
    }
}

async function loadRecommendations() {
    try {
        const data = await request(`${API}/recommendations.php?user_id=${userId()}&limit=8`);
        const list = data.recommendations || [];
        const panel = document.getElementById("recommendationContainer");

        if (!list.length) {
            panel.classList.remove("visible");
            panel.innerHTML = "";
            return;
        }

        panel.classList.add("visible");
        panel.innerHTML = `
            <h3>Recommended for user ${userId()}</h3>
            <div class="recommendation-list">
                ${list
                    .map(
                        (m) => `
                            <div class="recommendation-item">
                                <div><strong>${htmlSafe(m.title)}</strong></div>
                                <div>Score: ${m.recommendation_score}</div>
                                <div>Avg rating: ${Number(m.avg_rating || 0).toFixed(1)}</div>
                            </div>
                        `
                    )
                    .join("")}
            </div>
        `;
    } catch (_) {
        const panel = document.getElementById("recommendationContainer");
        panel.classList.remove("visible");
        panel.innerHTML = "";
    }
}

async function triggerReleaseNotifications() {
    try {
        const result = await request(`${API}/notifications.php`, {
            method: "POST",
            body: JSON.stringify({ days: 7 }),
        });
        alert(`Checked: ${result.checked}, Sent: ${result.sent}`);
    } catch (error) {
        alert(error.message);
    }
}

document.addEventListener("DOMContentLoaded", async () => {
    const profileButton = document.getElementById("profileButton");
    const profileDropdown = document.getElementById("profileDropdown");

    profileButton?.addEventListener("click", (event) => {
        event.stopPropagation();
        profileDropdown.classList.toggle("show");
    });

    document.addEventListener("click", () => {
        profileDropdown.classList.remove("show");
    });

    await refreshWatchlist();
    await loadMovies();
    await loadRecommendations();
    renderStars(0);
});
