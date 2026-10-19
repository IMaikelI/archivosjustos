Onload = false
function Send(Var, Post, Reload){
    const Data = Var;
    const Upload = new XMLHttpRequest();

    //Prepara la request
    Upload.open("POST", url);
    Upload.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
    Upload.send(Post + encodeURIComponent(Data))

    //Soft reset
    if (Reload == true){
        window.location.reload(false)
    } else{
        Onload = true
    }
};

let Get = (Page, Callback) =>  {   

    function Check() {
        if (Onload == true) {
            const recieve = new XMLHttpRequest()

        recieve.open("GET", Page);
        recieve.send();

        recieve.onload = function() {
            console.log("Raw " + recieve.responseText);
            var Data = JSON.parse(recieve.responseText); //Variable Data
            if (!Data) {
                Data = {Num: {}}
            }
            console.log(Data);
            Callback(Data) //Funcion (Data) = Data -- Callback es la funcion - Ver Rutinas.php
            Onload = false

            
            return
        };

        } else{
            window.setTimeout(Check, 100)
        }
    }
    Check()
    

};
