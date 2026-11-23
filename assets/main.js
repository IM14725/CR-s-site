const reportBtn = document.querySelector(".reportBtn");
const bookFormContainer = document.querySelector(".bookFormContainer");
const closeBtn = document.querySelector(".closeBtn");

reportBtn.addEventListener('click', ()=> {
    bookFormContainer.classList.remove('displayNone')
})

closeBtn.addEventListener('click', () => {
     bookFormContainer.classList.add('displayNone')
})
