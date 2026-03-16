// const accountBtn = document.getElementById("accountBtn");
// const dropdownMenu = document.getElementById("dropdownMenu");

// accountBtn.addEventListener("click", function () {
//     dropdownMenu.classList.toggle("show");
// });

// // Close when clicking outside
// window.addEventListener("click", function (e) {
//     if (!accountBtn.contains(e.target) && !dropdownMenu.contains(e.target)) {
//         dropdownMenu.classList.remove("show");
//     }
// });


// // script for the product cards

// document.addEventListener("DOMContentLoaded", function () {
//   const productCards = document.querySelectorAll(".product-card");

//   productCards.forEach((card) => {
//     const detailsBtn = card.querySelector(".details-btn");
//     const hireBtn = card.querySelector(".hire-btn");
//     const detailsBox = card.querySelector(".details-box");
//     const message = card.querySelector(".message");

//     detailsBtn.addEventListener("click", () => {
//       detailsBox.classList.toggle("active");

//       if (detailsBox.classList.contains("active")) {
//         detailsBtn.textContent = "Hide Details";
//       } else {
//         detailsBtn.textContent = "Product Details";
//       }
//     });

//     hireBtn.addEventListener("click", () => {
//       message.classList.add("show");
//       hireBtn.textContent = "Hired";
//       hireBtn.disabled = true;
//     });
//   });
// });

// // script for  brand section 
// document.addEventListener("DOMContentLoaded", () => {
//   const brandTrack = document.getElementById("brandTrack");

//   if (brandTrack) {
//     brandTrack.innerHTML += brandTrack.innerHTML;
//   }
// });

document.addEventListener("DOMContentLoaded", () => {
  // Account dropdown
  const accountBtn = document.getElementById("accountBtn");
  const dropdownMenu = document.getElementById("dropdownMenu");

  if (accountBtn && dropdownMenu) {
    accountBtn.addEventListener("click", (e) => {
      e.stopPropagation();
      dropdownMenu.classList.toggle("show");
    });

    document.addEventListener("click", (e) => {
      if (!accountBtn.contains(e.target) && !dropdownMenu.contains(e.target)) {
        dropdownMenu.classList.remove("show");
      }
    });
  }

  // Mobile nav toggle
  const navToggle = document.getElementById("navToggle");
  const navMenu = document.getElementById("navMenu");

  if (navToggle && navMenu) {
    navToggle.addEventListener("click", () => {
      navMenu.classList.toggle("show");
    });
  }

  // Product card actions using event delegation
  const productSection = document.querySelector(".parent-products");

  if (productSection) {
    productSection.addEventListener("click", (e) => {
      const detailsBtn = e.target.closest(".details-btn");
      const hireBtn = e.target.closest(".hire-btn");

      if (detailsBtn) {
        const card = detailsBtn.closest(".product-card");
        const detailsBox = card.querySelector(".details-box");

        if (detailsBox) {
          detailsBox.classList.toggle("active");
          detailsBtn.textContent = detailsBox.classList.contains("active")
            ? "Hide Details"
            : "Product Details";
        }
      }

      if (hireBtn) {
        const card = hireBtn.closest(".product-card");
        const message = card.querySelector(".message");

        if (message && !hireBtn.disabled) {
          message.classList.add("show");
          hireBtn.textContent = "Hired";
          hireBtn.disabled = true;
        }
      }
    });
  }

  // Brand slider duplication - lighter and only once
  const brandTrack = document.getElementById("brandTrack");

  if (brandTrack && !brandTrack.dataset.cloned) {
    const slides = Array.from(brandTrack.children);

    slides.forEach((slide) => {
      const clone = slide.cloneNode(true);
      clone.setAttribute("aria-hidden", "true");
      brandTrack.appendChild(clone);
    });

    brandTrack.dataset.cloned = "true";
  }
});


// ........................
// Script for the form
// ...............................

const loginTab = document.getElementById("loginTab");
const registerTab = document.getElementById("registerTab");
const loginFormBox = document.getElementById("loginFormBox");
const registerFormBox = document.getElementById("registerFormBox");
const goRegister = document.getElementById("goRegister");
const goLogin = document.getElementById("goLogin");
// const goRegister = document.getElementById("goRegister");
// const goLogin = document.getElementById("goLogin");

function showLogin() {
  loginTab.classList.add("active");
  registerTab.classList.remove("active");
  loginFormBox.classList.add("active");
  registerFormBox.classList.remove("active");
}

function showRegister() {
  registerTab.classList.add("active");
  loginTab.classList.remove("active");
  registerFormBox.classList.add("active");
  loginFormBox.classList.remove("active");
}

loginTab.addEventListener("click", showLogin);
registerTab.addEventListener("click", showRegister);
if (goRegister) goRegister.addEventListener("click", showRegister);
if (goLogin) goLogin.addEventListener("click", showLogin);
// goRegister.addEventListener("click", showRegister);
// goLogin.addEventListener("click", showLogin);

document.querySelectorAll(".toggle-password").forEach((button) => {
  button.addEventListener("click", () => {
    const targetInput = document.getElementById(button.dataset.target);
    const isPassword = targetInput.type === "password";
    targetInput.type = isPassword ? "text" : "password";
    button.textContent = isPassword ? "Hide" : "Show";
  });
});

const loginForm = document.getElementById("loginForm");
const registerForm = document.getElementById("registerForm");
const loginMessage = document.getElementById("loginMessage");
const registerMessage = document.getElementById("registerMessage");


// ---- REGISTER ----
document.getElementById('registerForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const name     = document.getElementById('registerName').value.trim();
    const email    = document.getElementById('registerEmail').value.trim();
    const password = document.getElementById('registerPassword').value.trim();
    const confirm  = document.getElementById('confirmPassword').value.trim();
    const role     = document.getElementById('registerRole').value;
    const msg      = document.getElementById('registerMessage');

    // ---- Validation checks ----
    if (!name || !email || !password || !confirm || !role) {
        msg.className = 'message error';
        msg.textContent = 'Please fill in all fields.';
        return;
    }

    if (password.length < 6) {
        msg.className = 'message error';
        msg.textContent = 'Password must be at least 6 characters.';
        return;
    }

    if (password !== confirm) {
        msg.className = 'message error';
        msg.textContent = 'Passwords do not match.';
        return;
    }

    // ---- All good — send to PHP ----
    const data = new FormData();
    data.append('full_name', name);
    data.append('email',     email);
    data.append('password',  password);
    data.append('role',      role);

    fetch('signup.php', { method: 'POST', body: data })
        .then(res => res.json())
        .then(res => {
            msg.textContent = res.message;
            msg.className = 'message ' + (res.success ? 'success' : 'error');
            if (res.success) document.getElementById('registerForm').reset();
        })
        .catch(() => {
            msg.className = 'message error';
            msg.textContent = 'Could not connect to server. Try again.';
        });
});

// ---- LOGIN ----
document.getElementById('loginForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const data = new FormData();
    data.append('email', document.getElementById('loginEmail').value);
    data.append('password', document.getElementById('loginPassword').value);
    data.append('role', document.getElementById('loginRole').value);

    fetch('login.php', { method: 'POST', body: data })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                window.location.href = res.redirect; // go to dashboard
            } else {
                const msg = document.getElementById('loginMessage');
                msg.textContent = res.message;
                msg.className = 'message error';
            }
        });
});