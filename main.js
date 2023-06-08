let xmlhttp = new XMLHttpRequest();
xmlhttp.onreadystatechange = function () {
    if (this.readyState == 4 && this.status == 200) {
        let element = document.getElementById("list-group");

        while (element.firstChild) {
            element.removeChild(element.firstChild);
        }

        let tracks = JSON.parse(this.responseText).tracks.items;

        for (let i = 0; i < tracks.length; i++) {
            let table = document.createElement("table");
            let leftDiv = document.createElement("td");
            let leftRow = document.createElement("tr");
            let rightDiv = document.createElement("td");
            let rightRow1 = document.createElement("tr");
            let rightRow2 = document.createElement("tr");

            let songNameDiv = document.createElement("div");
            songNameDiv.setAttribute("class", "d-flex");
            songNameDiv.setAttribute("align", "left");
            let songName = document.createElement("h5");
            songName.setAttribute("class", "mb-1");
            songName.appendChild(document.createTextNode(tracks[i]["name"]));
            songNameDiv.appendChild(songName);

            let artistDiv = document.createElement("div");
            let artistName = document.createTextNode(tracks[i]["artists"][0]["name"] + " ♪ " + (time(tracks[i]["duration_ms"]) + " ❖ " + (tracks[i]["album"]["release_date"]).substring(0, 4)));
            artistDiv.setAttribute("align", "left");
            artistDiv.appendChild(artistName);

            let songNumberDiv = document.createElement("h5");
            let songNumber = "";
            if ((i + 1) < 10) songNumber = "#" + (i + 1) + "\xa0\xa0\xa0";
            else songNumber = "#" + (i + 1) + "\xa0\xa0";
            songNumberDiv.appendChild(document.createTextNode(songNumber));

            leftRow.appendChild(songNumberDiv);
            rightRow1.appendChild(songNameDiv);
            rightRow2.appendChild(artistDiv);

            leftDiv.appendChild(leftRow);
            rightDiv.appendChild(rightRow1);
            rightDiv.appendChild(rightRow2);

            table.appendChild(leftDiv);
            table.appendChild(rightDiv);

            let track = tracks[i];
            let listItem = document.createElement("a");
            listItem.appendChild(table);
            listItem.setAttribute("class", "list-group-item list-group-item-action " + primaryBg + " " + primaryFg);
            listItem.setAttribute("data-toggle", "list");
            listItem.setAttribute("href", "#");
            listItem.setAttribute("songId", track["id"]);
            listItem.setAttribute("name", track["name"]);
            listItem.setAttribute("artist", track["artists"][0]["name"]);
            listItem.setAttribute("duration", track["duration_ms"]);

            listItem.addEventListener("click", function (event) {
                let selectedItem = event.target.closest("a");
                requestGetCooldown.open("GET", "GetCooldown.php", false);
                requestGetCooldown.send();

                if (isOnCooldown) {
                    alert("Cooldown is still on. Please try again later.");
                }
                else if (parseInt(selectedItem.getAttribute("duration")) > 999999999) { // TODO: use 600000 as max song duration
                    alert("Song is too long, maximum duration is 10 minutes");
                }
                else {
                    document.getElementById("confirmSongName").innerHTML = selectedItem.getAttribute("artist") + " - " + selectedItem.getAttribute("name");
                    $('#exampleModal').modal('show');
                    $("#addsong").off("click").on("click", async function () {
                        let message = document.getElementById("messageInput").value;
                        let requestAddToQueue = new XMLHttpRequest();
                        let songId = selectedItem.getAttribute("songId");

                        requestAddToQueue.open("POST", "AddToQueue.php?songId=" + songId + "&message=" + message, true);
                        requestAddToQueue.send();
                        requestGetCooldown.open("GET", "GetCooldown.php");
                        requestGetCooldown.send();

                        $("#success-alert").fadeTo(2000, 500).slideUp(500, function () {
                            $("#success-alert").slideUp(500);
                        });
                        $('#exampleModal').modal('hide');

                        document.getElementById("messageInput").value = "";

                        await sleep(1000);
                        getQueue();
                    });
                }
            });
            element.appendChild(listItem);
        }
    }
};

let searchInput = document.getElementById("searchInput");
searchInput.addEventListener("keyup", function (event) {
    if (event.keyCode === 13) {
        event.preventDefault();
        onSearchClick();
    }
});

primaryBg = "bg-light";
secondaryBg = "bg-white";
primaryFg = "text-dark";

$("#success-alert").hide();
function onSearchClick() {
    let query = document.getElementById("searchInput").value;

    if (query != '') {
        xmlhttp.open("GET", "Search.php?text=" + query, true);
        xmlhttp.send();
    }
}

document.getElementById("boat").addEventListener("click", onBoatClick, false);

function getQueue() {
    fetch("GetQueue.php")
        .then(response => response.text())
        .then(data => {
            let tracks = JSON.parse(data);
            let element = document.getElementById("playList");
            let queueCountElement = document.getElementById("playlistBtn");
            queueCountElement.setAttribute("style", "color: #2275FC");
            element.setAttribute("style", "margin: 10px 0 10px 0;");

            while (element.firstChild) {
                element.removeChild(element.firstChild);
            }
            while (queueCountElement.firstChild) {
                queueCountElement.removeChild(queueCountElement.firstChild);
            }

            if (tracks.length == 1 && tracks[0] == null) {
                queueCountElement.appendChild(document.createTextNode("View Queue (0)"));
            }
            else {
                queueCountElement.appendChild(document.createTextNode("View Queue (" + (tracks.length) + ")"));
                for (let i = 0; i < tracks.length; i++) {
                    let track = tracks[i];

                    var asd = document.createElement("span");
                    asd.innerHTML = (track["artist"] + " - " + track["song_name"]);
                    element.appendChild(asd);
                    asd.setAttribute("class", "d-inline-block text-truncate m-0 pt-1 pb-1");

                    if ((i % 2) === 0) {
                        asd.setAttribute("style", "color: black; background: #f0fafe");
                    } else {
                        asd.setAttribute("style", "color: black;");
                    }

                    element.setAttribute("align", "center");
                }
            }
        });
}

getQueue();

let requestGetCurrent = new XMLHttpRequest();
requestGetCurrent.onreadystatechange = function () {
    let element = document.getElementById("currentSong");
    let lengthElement = document.getElementById("songLength");
    let messageElement = document.getElementById("message");

    if (this.readyState == 4 && this.status == 200) {
        while (element.firstChild) {
            element.removeChild(element.firstChild);
        }
        while (lengthElement.firstChild) {
            lengthElement.removeChild(lengthElement.firstChild);
        }
        while (messageElement.firstChild) {
            messageElement.removeChild(messageElement.firstChild);
        }

        let obj = JSON.parse(this.responseText);
        if (obj == null) {
            location.href = "index.php";
        } else if (obj.track == null) {
            document.getElementById("progressContainer").style.display = "none";
            messageElement.innerHTML = ("Paused");
        }
        else {
            let song = obj.track.item;
            let progress = obj.track.progress_ms;
            let duration = song.duration_ms;
            let progressBar = document.getElementById("progressBar");
            let durationElement = document.getElementById("songDuration");
            let diff = duration - progress;
            let percentage = progress / duration * 100;

            if (obj.message && obj.message != "") {
                messageElement.appendChild(document.createTextNode("\"" + obj.message + "\""));
            }

            element.appendChild(document.createTextNode(song.artists[0].name + " - " + song.name));
            lengthElement.appendChild(document.createTextNode(new Date(duration).toISOString().substr(14, 5)));
            durationElement.appendChild(document.createTextNode(new Date((diff > 0 ? progress : 0)).toISOString().substr(14, 5)));
            progressBar.setAttribute("style", "width: " + percentage + "%")

            var timer = setInterval(async function () {
                diff = duration - progress;
                percentage = progress / duration * 100;
                progress = progress + 1000;

                progressBar.setAttribute("style", "width: " + percentage + "%")

                if (diff <= 0) {
                    while (durationElement.firstChild) {
                        durationElement.removeChild(durationElement.firstChild);
                    }

                    progressBar.setAttribute("style", "width: " + 0 + "%")
                    clearTimeout(timer);

                    await sleep(3000);

                    requestGetCurrent.open("GET", "GetCurrent.php");
                    requestGetCurrent.send();
                    getQueue();
                }

                while (durationElement.firstChild) {
                    durationElement.removeChild(durationElement.firstChild);
                }
                durationElement.appendChild(document.createTextNode(new Date((diff > 0 ? progress : 0)).toISOString().substr(14, 5)));
            }, 1000);
        }
    }
}

requestGetCurrent.open("GET", "GetCurrent.php");
requestGetCurrent.send();

let requestGetCooldown = new XMLHttpRequest();
let isOnCooldown = true;

requestGetCooldown.onreadystatechange = function () {
    let element = document.getElementById("cooldown");
    if (this.readyState == 4 && this.status == 200) {
        let counter = this.responseText;

        while (element.firstChild) {
            element.removeChild(element.firstChild);
        }

        if (counter <= 0) {
            isOnCooldown = false;
            return;
        } else {
            element.appendChild(document.createTextNode("Cooldown for this IP ⧖ " + new Date(1000 * counter).toISOString().substr(14, 5)));
            isOnCooldown = true;
        }

        var timer = setInterval(function () {
            counter--;

            var element = document.getElementById("cooldown");
            while (element.firstChild) {
                element.removeChild(element.firstChild);
            }

            if (counter <= 0) {
                clearTimeout(timer);
            } else {
                element.appendChild(document.createTextNode("Cooldown for this IP ⧖ " + new Date(1000 * counter).toISOString().substr(14, 5)));
            }
        }, 1000);
    }
}

requestGetCooldown.open("GET", "GetCooldown.php");
requestGetCooldown.send();

function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

function time(ms) {
    let date = new Date(ms);
    let seconds = date.getSeconds();

    if (seconds < 10) {
        return date.getMinutes() + ":" + "0" + date.getSeconds();
    } else {
        return date.getMinutes() + ":" + date.getSeconds();
    }
}

const themes = {
    SUMMER: 'summer',
    XMAS: 'xmas'
}

let theme = themes.SUMMER;
let boat = document.getElementById("boat");
let bg = document.getElementById("headerBg");
let texts = window['texts'];
let lang = window['lang'];
let textCounter = 0;
let leftBorder = 0;
let canClickBoat = true;

switch (theme) {
    case themes.SUMMER:
        bg.setAttribute("src", "images/bg_summer.png");
        boat.setAttribute("src", "images/boat.png");
        leftBorder = 200;
        readTextFileToArray("texts/texts-summer.txt", 'texts');
        break;
    case themes.XMAS:
        bg.setAttribute("src", "images/bg_xmas.png");
        boat.setAttribute("src", "images/santa.png");
        readTextFileToArray("texts/texts-xmas.txt", 'texts');
        readTextFileToArray("texts/lang.txt", 'lang');
        leftBorder = 0;
        break;
}

texts = window['texts'];
lang = window['lang'];

function moveBoat() {
    var elem = document.getElementById("boatHolder");
    var boat = document.getElementById("boat");
    var pos = leftBorder;
    setInterval(frame, 320);
    var goingRight = true;
    elem.style.visibility = "visible";

    function frame() {
        if (pos >= 800) {
            goingRight = false;
        }

        if (pos <= leftBorder) {
            goingRight = true;
        }

        if (goingRight) {
            pos++;
            elem.style.left = pos / 10 + '%';
            boat.style.transform = "scaleX(1)";
        } else {
            pos--;
            elem.style.left = pos / 10 + '%';
            boat.style.transform = "scaleX(-1)";
        }
    }
}

async function readTextFileToArray(file, name) {
    var rawFile = new XMLHttpRequest();
    rawFile.open("GET", file, false);
    rawFile.onreadystatechange = function () {
        if (rawFile.readyState === 4) {
            if (rawFile.status === 200 || rawFile.status == 0) {
                var allText = rawFile.responseText;
                window[name] = allText.split("\n");
            }
        }
    }
    rawFile.send(null);
}

async function onBoatClick() {
    if (!canClickBoat) return;

    let boat = document.getElementById("boat");
    let boatText = document.getElementById("boatText");
    boatText.setAttribute("style", "visibility: visible;");
    boat.setAttribute("class", "shake");

    boatText.innerHTML = "";

    canClickBoat = false;

    switch (theme) {
        case "summer":
            await onBoatClickSummer();
            break;
        case "xmas":
            await onBoatClickXmas();
            break;
    }

    textCounter++;

    if (textCounter > texts.length - 1) textCounter = 0;

    canClickBoat = true;

    boat.setAttribute("class", "none");
    boatText.setAttribute("style", "visibility: hidden;");
}

async function onBoatClickSummer() {
    await typewriterText(boatText, texts[textCounter]);
}

async function onBoatClickXmas() {
    if (textCounter >= texts.length) {
        await sleep(10000);
        await typewriterText(boatText, "... What?");
        await sleep(10000);
        boatText.setAttribute("style", "font-style: italic;");

        await typewriterText(boatText, ". . .", 666);
        await sleep(3000);
        await typewriterText(boatText, "Ỳ̷̧̛̗̞͔̬͔͇͆̓͘ͅō̥͓̟̘̩̔̌̽͒͂̕̕͜͝ų̸̛͎͙̟͓̠͔͐̾̊̈͌͛͒ ẅ̸̧͓̲̹͙̘̱͓͂̎̅̾͒̒ͅͅĩ̧̛̱̳̣̙͔͚̫̀͗͘͝ĺ̸̢̼̲̺̯̜͙̟̲̉̈́̽͝͠͠l̵͓̱̩̟͉͉̟̣͈̣͂̍̐̈́̍ d̷̝̭̙̦̓̇̀̄̔͂̌̓̀͆͜ͅí̢̜̞̝͔̔͛͋̾͜͢ẻ̸͙͚͈͓̲̇̎̉̚̕͢", 5);
        await sleep(10000);

        while (true) {
            await typewriterText(boatText, utterTheWordsOfChaos());
            await sleep(3000);
            textCounter++;

            if (textCounter == 222) {
                textCounter = 0;
                break;
            }
        }
    }

    if (textCounter == texts.length - 2) {
        boatText.setAttribute("style", "font-style: italic;");
    } else {
        boatText.setAttribute("style", "font-style: normal;");
    }

    if (texts[textCounter] == "B̨͚̤̱͖̲̀͋͂̋͟͝͞͝r̛͈̯̠̤̫̰̭̦̯͛̈͐͋̀͗͠ͅę̢̣͇̼̖̘̘̲̟̊̅̍͋̃͗͂͘͠ȁ̧̧̙̮͕̠̝̥̆̍͋̀̈́̈̔̊͋k̵͈͎͎̠̟͎̙͉͕͑̑̆̄̉̕͟͠i̧͇̻͎̫͉̳̘̒͗̓̈̕͞͞ͅn̴̢̧̨̠̯͕͕̉̀̒̈́̕ḡ̵͍̻̳̞̣̄̄́̍͘͝͞ͅ o̵̡̟͓͍̰̫̓̾̅̉́̿͢͞͝f̢̥̞̜͕̳̈̀͒̒̓́̌͘ ț̶̨͑̅͋̌̄͂͜͢͟͜͝ͅh̵̖͇̻̞̼͎͐͌͆͊̋̾͘͢͠e̴̫̥̪̹̣͎͋́͐̇͛͞͝ s̛͕̘͇̫͚̗͇͙̏̉̾̅̊̊͟͠͠e̢̛̟̪̙̣͓͙͔̗̽͊̍̍̕͢͝á̶̛̜̪͙̪͎͛̀̈́͘͟͢͝͞ļ̱̖̬͎̱͐͋̿̆͞ h̸̺̟͕͍͓̘̽̋͑̃̉̔̎̾̕̕͟ă̧̬̭̜͍͈̯̊̊̆͒̊̂́͜͟͝͡ş̧̘̫̝͚̙͚̏̓̈͋͢͠ b̺̱̬̮͙̂͑̊̃̊̍͘͢ẹ̵̖͈̖̩͖͗̌̋͐́̍̾g̨͇͚̭͔͙̠͗͂̓̊͑̽̓͝ͅú̥̟͇̼͇̠̝͉̬̺̆͒̄̊̌͋̋͘n̲̰̗͉̬͔͋͋̾̋̽̕͘.̶̦̦̦̹̙̯͓̬̲͋̐̒̂̍͜" || texts[textCounter] == "Ţ̭̬̜̣̹̤͖̑̓̓̃̈̽̓͘ͅh̡͎͉̰̜͎̣̝͓̍͂̂͋̎̅̀̇͘̚ẽ͈̣̘̺͓̤͚̘̏̾͛͘͝r̵̢̧̨̙͕̲͕̙͆̅́̒̽͡͞͠͝ě͔͇͚̬̦̔̍̀͆̀͘͜͞ i̷̧̧̫̙̹̦̤̅̀̎̀̑̒̍̎͠ş̶͓̝̹̩͈͊̂̑͛͒̚͠͡ n̵̹̰̙̩̪̻͋̈́̏͗̿̿̓̅͠ȏ̧͔̬͙͓̯͔̰̱͌͑̀̎͌͜ d͎̟̞̲̓̎͋͗̆͑̂͟ã͍͇̞̞̯͚͈̝̤͑̃̆̃̍͊̿͛r̻̹͓̩͚͍̍͐̈́̂͐̚͢k̸̡̨̧̼͓̝̉́̊͐̚ͅe͚̥̟͈͙̫̮̎̂̃̆͘͜͢͡r͕͚̦̖̻̯͛̎̏͐̀̿͘͢ m̴̨͓̳̹̩̒͗̌̅̊̀̕͝͞á̡͔͙͕̲̺̺͈͊͑̾̚͟͝g̨̮̪̜͖͉̦̬̙͖̈́͑̌̄́̒͞į̸̰͔̣̤͂̈͐̏̈́̑̒͜͢c̶̢͍̯͚̼͔̳̫͐̏̀̓̽̍͞ͅ t̸̡̬̙̼̲̪̜̭̱̑̈̀̂͌̃h̵̡̨̺̤̟̩̄̓̃̏̎͠͝ą̹̬̄̿̀̆̈́̀̀̈̈͢ͅñ̴͈̙̪͖͕̭̆͑͆͆̐̃͠ ḍ̛̻̖̰̰͇̲̼̩͆͌̇̄͊͞è̵̥͉̘͈̣̫̐̑̎́̃̆͘ǟ̢̡̖̣̭̙̐͒̑͗̿͊͗̈͢t̴̻̥̮̱̜̮̙̒͂̈́́̏̈́̽̑̚͜͡h̸̢̡̖͚̥͇̓́̄̎̈̿̏͛͟͢") {
        await typewriterText(boatText, texts[textCounter], 5);
    }
    else {
        await typewriterText(boatText, texts[textCounter]);
    }
}

async function typewriterText(element, text, interval = 50) {
    for (let i = 0; i < text.length; i++) {
        element.innerHTML += text.charAt(i);
        await sleep(interval);
    }
    await sleep(1000);
    boatText.innerHTML = "";
}

function utterTheWordsOfChaos() {
    let s = '... ';

    for (let i = 0; i < Math.random() * 5 + 1; i++) {
        s += lang[Math.floor(Math.random() * lang.length)] + ' ';
    }

    s += '...';

    return s;
}

moveBoat();