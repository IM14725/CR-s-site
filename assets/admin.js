const fullName = document.querySelector("#fullName");
const defaultPassword = document.querySelector("#defaultPassword");

fullName.addEventListener("input", () => {
    const name = fullName.value.trim();
    if (name.length > 0) {
        const password = name.split(" ")[0] + "@123";
        defaultPassword.value = password;
    } else {
        defaultPassword.value = "";
    }
});
const searchResultContainer = document.querySelector('.searchResultContainer')
const crContainer = [...document.querySelectorAll(".crContainer")];
const serchInput = document.querySelectorAll('.serchInput')
const searchBtn = document.querySelectorAll('.searchBtn')
const regNumbers = document.querySelectorAll('.regNumber')
const resultContainer = document.querySelector('.resultContainer')

const closeBtn2 = document.querySelector('.closeBtn2')
closeBtn2.addEventListener('click', () => {
    searchResultContainer.classList.add('displayNone')
})

searchBtn[0].addEventListener('click', () => {
    
    if(serchInput[0].value.length > 2) {
     searchFunction(serchInput[0])
    } else {
        alert('input should have alteast four character')
    }
}) 

searchBtn[1].addEventListener('click', () => {
    
    if(serchInput[1].value.length > 2) {
     searchFunction(serchInput[1])
    } else {
        alert('input should have alteast four character')
    }
}) 




function searchFunction(serchInput) {
    const result = crContainer.filter(container => {
        const regNum = container.querySelector('.regNumber')
        return regNum.textContent.includes(serchInput.value)
    })
    
    

    if(result.length < 1) {
        let searchResult = document.createElement('div');
        searchResult.classList.add('searchResult')
        searchResult.innerHTML = `
        <br><br>
        <h2 class="notFound"> <i class="fa-regular fa-face-sad-tear"></i> </h2>
            <h2 class="notFound">No results math your search .... !</span></h2>
            <br><br>
        `
        resultContainer.append(searchResult)
         searchResultContainer.classList.remove('displayNone')
    }
    result.forEach(value => {
        let regNo = value.querySelector('.regNumber').textContent
        const UserInfo = value.querySelectorAll('.userInfo')
        let userName = UserInfo[0].textContent;
        let course = UserInfo[1].textContent;
        let level = UserInfo[2].textContent;
        console.log(regNo, userName, course, level)
        let searchResult = document.createElement('div');
        searchResult.classList.add('searchResult')
    
        searchResult.innerHTML = `
                <p><span>Reg Number:</span> NIT/BIT/2024/2441 </p> 
                <p><span>Name:</span> Dotto Godfrey</p>
                <p><span>Course:</span> Bachelor of Information Techology</p>
                <p><span>Level:</span> 8, stream A</p>
                <hr>
                <div class="actionBtnContainer">
                    <button class="actionBtn edit"><i class="fa-solid fa-pen-to-square"></i> Edit</button>
                    <button class="actionBtn delete"><i class="fa-solid fa-trash"></i> Delete</button>
                </div> `       
      resultContainer.append(searchResult) 
      if(searchResultContainer.classList.contains('displayNone')) {
          searchResultContainer.classList.remove('displayNone')
      }
     
    })
}
