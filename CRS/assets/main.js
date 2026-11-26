const reportBtn = document.querySelector(".reportBtn");
const bookFormContainer = document.querySelector(".bookFormContainer");
const closeBtn = document.querySelectorAll(".closeBtn");

reportBtn.addEventListener('click', ()=> {
    bookFormContainer.classList.remove('displayNone')
})

closeBtn[0].addEventListener('click', () => {
     bookFormContainer.classList.add('displayNone')
})



const bookBtn = document.querySelectorAll(".bookBtn");
const confirmContainer = document.querySelector('.confirmContainer')
const confirmInput = document.querySelector('#confirm');
const cancel = document.querySelector('.cancel')
bookBtn.forEach(btn => {
    btn.addEventListener('click', event => {
        const vennueContainer = event.target.closest('.vennueContainer')
        let vennue = vennueContainer.querySelector('.vennue').textContent;
        confirmContainer.classList.remove('displayNone')
         confirmInput.value = vennue;

    })
})

closeBtn[1].addEventListener('click', () => {      
     confirmContainer.classList.add('displayNone')
})  
                   
cancel.addEventListener('click', () => {      
     confirmContainer.classList.add('displayNone') 
})   

const currentYear = document.querySelector('#currentYear')
currentYear.textContent = new Date().getFullYear();