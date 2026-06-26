(function () {
  document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('.animated-login');

    if (!form || typeof TweenMax === 'undefined') return;

    // ==== ELEMENT SELECTION ====
    var identifierLabel = form.querySelector('#loginIdentifierLabel');
    var identifierInput = form.querySelector('#loginIdentifier');
    var passwordInput = form.querySelector('#loginPassword');
    var showPasswordCheck = form.querySelector('#showPasswordCheck');
    var showPasswordToggle = form.querySelector('#showPasswordToggle');

    var mySVG = form.querySelector('.svgContainer');
    var twoFingers = form.querySelector('.twoFingers');
    var armL = form.querySelector('.armL');
    var armR = form.querySelector('.armR');
    var eyeL = form.querySelector('.eyeL');
    var eyeR = form.querySelector('.eyeR');
    var nose = form.querySelector('.nose');
    var mouth = form.querySelector('.mouth');
    var mouthBG = form.querySelector('.mouthBG');
    var mouthSmallBG = form.querySelector('.mouthSmallBG');
    var mouthMediumBG = form.querySelector('.mouthMediumBG');
    var mouthLargeBG = form.querySelector('.mouthLargeBG');
    var mouthMaskPath = form.querySelector('#mouthMaskPath');
    var mouthOutline = form.querySelector('.mouthOutline');
    var tooth = form.querySelector('.tooth');
    var tongue = form.querySelector('.tongue');
    var chin = form.querySelector('.chin');
    var face = form.querySelector('.face');
    var eyebrow = form.querySelector('.eyebrow');
    var outerEarL = form.querySelector('.earL .outerEar');
    var outerEarR = form.querySelector('.earR .outerEar');
    var earHairL = form.querySelector('.earL .earHair');
    var earHairR = form.querySelector('.earR .earHair');
    var hair = form.querySelector('.hair');
    var bodyBG = form.querySelector('.bodyBGnormal');
    var bodyBGchanged = form.querySelector('.bodyBGchanged');

    // ==== STATE VARS ====
    var activeElement, curIndex, screenCenter, svgCoords, inputCoords, inputScrollMax;
    var chinMin = 0.5, dFromC, mouthStatus = 'small', blinking, eyeScale = 1;
    var eyesCovered = false, showPasswordClicked = false;
    var eyeLCoords, eyeRCoords, noseCoords, mouthCoords;

    // ==== HELPER FUNCTIONS ====
    function getAngle(x1, y1, x2, y2) {
      return Math.atan2(y1 - y2, x1 - x2);
    }

    function getPosition(el) {
      var xPos = 0, yPos = 0;
      while (el) {
        if (el.tagName === 'BODY') {
          var xScroll = el.scrollLeft || document.documentElement.scrollLeft;
          var yScroll = el.scrollTop || document.documentElement.scrollTop;
          xPos += el.offsetLeft - xScroll + el.clientLeft;
          yPos += el.offsetTop - yScroll + el.clientTop;
        } else {
          xPos += el.offsetLeft - el.scrollLeft + el.clientLeft;
          yPos += el.offsetTop - el.scrollTop + el.clientTop;
        }
        el = el.offsetParent;
      }
      return { x: xPos, y: yPos };
    }

    function getRandomInt(max) {
      return Math.floor(Math.random() * Math.floor(max));
    }

    function setMouthShape(target) {
      if (!target) return;

      var targetPath = target.getAttribute('d');
      if (!targetPath) return;

      [mouthBG, mouthOutline, mouthMaskPath].forEach(function (element) {
        if (element) element.setAttribute('d', targetPath);
      });
    }

    // ==== FACE ANIMATION ====
    function calculateFaceMove() {
      var caretPos = identifierInput.selectionEnd;
      if (caretPos == null || caretPos === 0) caretPos = identifierInput.value.length;

      var div = document.createElement('div');
      var span = document.createElement('span');
      var style = window.getComputedStyle(identifierInput);
      [].forEach.call(style, function (prop) {
        div.style[prop] = style[prop];
      });

      div.style.position = 'absolute';
      div.style.visibility = 'hidden';
      div.style.whiteSpace = 'pre';
      document.body.appendChild(div);
      div.textContent = identifierInput.value.substr(0, caretPos);
      span.textContent = identifierInput.value.substr(caretPos) || '.';
      div.appendChild(span);

      var caretCoords = {};
      var targetX;
      var targetY = inputCoords.y + 25;
      if (identifierInput.scrollWidth <= inputScrollMax) {
        caretCoords = getPosition(span);
        targetX = caretCoords.x;
        targetY = caretCoords.y + 25;
        dFromC = screenCenter - targetX;
      } else {
        targetX = inputCoords.x + inputScrollMax;
        dFromC = screenCenter - targetX;
      }

      var eyeLAngle = getAngle(eyeLCoords.x, eyeLCoords.y, targetX, targetY);
      var eyeRAngle = getAngle(eyeRCoords.x, eyeRCoords.y, targetX, targetY);
      var noseAngle = getAngle(noseCoords.x, noseCoords.y, targetX, targetY);
      var mouthAngle = getAngle(mouthCoords.x, mouthCoords.y, targetX, targetY);

      var eyeLX = Math.cos(eyeLAngle) * 20, eyeLY = Math.sin(eyeLAngle) * 10;
      var eyeRX = Math.cos(eyeRAngle) * 20, eyeRY = Math.sin(eyeRAngle) * 10;
      var noseX = Math.cos(noseAngle) * 23, noseY = Math.sin(noseAngle) * 10;
      var mouthX = Math.cos(mouthAngle) * 23, mouthY = Math.sin(mouthAngle) * 10;
      var mouthR = Math.cos(mouthAngle) * 6;
      var chinX = mouthX * 0.8, chinY = mouthY * 0.5;
      var chinS = 1 - ((dFromC * 0.15) / 100);
      if (chinS > 1) {
        chinS = 1 - (chinS - 1);
        if (chinS < chinMin) chinS = chinMin;
      }

      var faceX = mouthX * 0.3, faceY = mouthY * 0.4;
      var faceSkew = Math.cos(mouthAngle) * 5;
      var eyebrowSkew = Math.cos(mouthAngle) * 25;
      var outerEarX = Math.cos(mouthAngle) * 4;
      var outerEarY = Math.cos(mouthAngle) * 5;
      var hairX = Math.cos(mouthAngle) * 6;
      var hairS = 1.2;

      TweenMax.to(eyeL, 1, { x: -eyeLX, y: -eyeLY, ease: Expo.easeOut });
      TweenMax.to(eyeR, 1, { x: -eyeRX, y: -eyeRY, ease: Expo.easeOut });
      TweenMax.to(nose, 1, { x: -noseX, y: -noseY, rotation: mouthR, transformOrigin: "center center", ease: Expo.easeOut });
      TweenMax.to(mouth, 1, { x: -mouthX, y: -mouthY, rotation: mouthR, transformOrigin: "center center", ease: Expo.easeOut });
      TweenMax.to(chin, 1, { x: -chinX, y: -chinY, scaleY: chinS, ease: Expo.easeOut });
      TweenMax.to(face, 1, { x: -faceX, y: -faceY, skewX: -faceSkew, transformOrigin: "center top", ease: Expo.easeOut });
      TweenMax.to(eyebrow, 1, { x: -faceX, y: -faceY, skewX: -eyebrowSkew, transformOrigin: "center top", ease: Expo.easeOut });
      TweenMax.to(outerEarL, 1, { x: outerEarX, y: -outerEarY, ease: Expo.easeOut });
      TweenMax.to(outerEarR, 1, { x: outerEarX, y: outerEarY, ease: Expo.easeOut });
      TweenMax.to(earHairL, 1, { x: -outerEarX, y: -outerEarY, ease: Expo.easeOut });
      TweenMax.to(earHairR, 1, { x: -outerEarX, y: outerEarY, ease: Expo.easeOut });
      TweenMax.to(hair, 1, { x: hairX, scaleY: hairS, transformOrigin: "center bottom", ease: Expo.easeOut });

      document.body.removeChild(div);
    }

    function updateMouth(value) {
      var typedLength = value.trim().length;
      if (!typedLength) {
        mouthStatus = "small";
        setMouthShape(mouthSmallBG);
        TweenMax.to([eyeL, eyeR], 1, { scaleX: 1, scaleY: 1, ease: Expo.easeOut });
        TweenMax.to(tooth, 1, { x: 0, y: 0, ease: Expo.easeOut });
        TweenMax.to(tongue, 1, { y: 0, ease: Expo.easeOut });
        eyeScale = 1;
      } else {
        if (typedLength >= 4) {
          mouthStatus = "large";
          setMouthShape(mouthLargeBG);
          TweenMax.to(tooth, 1, { x: 3, y: -2, ease: Expo.easeOut });
          TweenMax.to(tongue, 1, { y: 2, ease: Expo.easeOut });
          TweenMax.to([eyeL, eyeR], 1, { scaleX: .65, scaleY: .65, ease: Expo.easeOut, transformOrigin: "center center" });
          eyeScale = .65;
        } else {
          mouthStatus = "medium";
          setMouthShape(mouthMediumBG);
          TweenMax.to(tooth, 1, { x: 0, y: 0, ease: Expo.easeOut });
          TweenMax.to(tongue, 1, { x: 0, y: 1, ease: Expo.easeOut });
          TweenMax.to([eyeL, eyeR], 1, { scaleX: .85, scaleY: .85, ease: Expo.easeOut });
          eyeScale = .85;
        }
      }
    }

    function resetFace() {
      TweenMax.to([eyeL, eyeR], 1, { x: 0, y: 0, ease: Expo.easeOut });
      TweenMax.to(nose, 1, { x: 0, y: 0, scaleX: 1, scaleY: 1, ease: Expo.easeOut });
      TweenMax.to(mouth, 1, { x: 0, y: 0, rotation: 0, ease: Expo.easeOut });
      TweenMax.to(chin, 1, { x: 0, y: 0, scaleY: 1, ease: Expo.easeOut });
      TweenMax.to([face, eyebrow], 1, { x: 0, y: 0, skewX: 0, ease: Expo.easeOut });
      TweenMax.to([outerEarL, outerEarR, earHairL, earHairR, hair], 1, { x: 0, y: 0, scaleY: 1, ease: Expo.easeOut });
      setMouthShape(mouthSmallBG);
      eyeScale = 1;
      mouthStatus = "small";
    }

    // ==== EVENTS ====
    function onIdentifierInput() {
      calculateFaceMove();
      updateMouth(identifierInput.value);
    }

    function onIdentifierFocus(e) {
      activeElement = "identifier";
      e.target.parentElement.classList.add("focusWithText");
      onIdentifierInput();
    }

    function onIdentifierBlur(e) {
      activeElement = null;
      setTimeout(function () {
        if (!identifierInput.value) e.target.parentElement.classList.remove("focusWithText");
        resetFace();
      }, 100);
    }

    function onPasswordFocus() {
      activeElement = "password";
      if (!eyesCovered) coverEyes();
    }

    function onPasswordBlur() {
      activeElement = null;
      setTimeout(function () {
        if (activeElement !== "toggle" && activeElement !== "password") uncoverEyes();
      }, 100);
    }

    function onPasswordToggleFocus() {
      activeElement = "toggle";
      if (!eyesCovered) coverEyes();
    }

    function onPasswordToggleBlur() {
      activeElement = null;
      if (!showPasswordClicked) {
        setTimeout(function () {
          if (activeElement !== "password" && activeElement !== "toggle") uncoverEyes();
        }, 100);
      }
    }

    function onPasswordToggleMouseDown() {
      showPasswordClicked = true;
    }

    function onPasswordToggleMouseUp() {
      showPasswordClicked = false;
    }

    function onPasswordToggleClick(e) {
      e.target.focus();
    }

    function onPasswordToggleChange(e) {
      setTimeout(function () {
        if (e.target.checked) {
          passwordInput.type = "text";
          spreadFingers();
        } else {
          passwordInput.type = "password";
          closeFingers();
        }
      }, 100);
    }

    function coverEyes() {
      TweenMax.killTweensOf([armL, armR]);
      TweenMax.set([armL, armR], { visibility: "visible" });
      TweenMax.to(armL, .45, { x: -93, y: 10, rotation: 0, ease: Quad.easeOut });
      TweenMax.to(armR, .45, { x: -93, y: 10, rotation: 0, ease: Quad.easeOut, delay: .1 });
      TweenMax.to(bodyBG, .45, { morphSVG: bodyBGchanged, ease: Quad.easeOut });
      eyesCovered = true;
    }

    function uncoverEyes() {
      TweenMax.killTweensOf([armL, armR]);
      TweenMax.to(armL, 1.35, { y: 220, rotation: 105, ease: Quad.easeOut });
      TweenMax.to(armR, 1.35, { y: 220, rotation: -105, ease: Quad.easeOut, delay: .1, onComplete: function () {
        TweenMax.set([armL, armR], { visibility: "hidden" });
      }});
      TweenMax.to(bodyBG, .45, { morphSVG: bodyBG, ease: Quad.easeOut });
      eyesCovered = false;
    }

    function spreadFingers() {
      TweenMax.to(twoFingers, .35, { transformOrigin: "bottom left", rotation: 30, x: -9, y: -2, ease: Power2.easeInOut });
    }

    function closeFingers() {
      TweenMax.to(twoFingers, .35, { transformOrigin: "bottom left", rotation: 0, x: 0, y: 0, ease: Power2.easeInOut });
    }

    // ==== BLINK ====
    function startBlinking(delay) {
      if (delay) delay = getRandomInt(delay); else delay = 1;
      blinking = TweenMax.to([eyeL, eyeR], .1, {
        delay: delay, scaleY: 0, yoyo: true, repeat: 1,
        transformOrigin: "center center",
        onComplete: function () { startBlinking(12); }
      });
    }

    // ==== INIT ====
    function initLoginForm() {
      svgCoords = getPosition(mySVG);
      inputCoords = getPosition(identifierInput);
      screenCenter = svgCoords.x + (mySVG.offsetWidth / 2);
      eyeLCoords = { x: svgCoords.x + 84, y: svgCoords.y + 76 };
      eyeRCoords = { x: svgCoords.x + 113, y: svgCoords.y + 76 };
      noseCoords = { x: svgCoords.x + 97, y: svgCoords.y + 81 };
      mouthCoords = { x: svgCoords.x + 100, y: svgCoords.y + 100 };

      identifierInput.addEventListener('focus', onIdentifierFocus);
      identifierInput.addEventListener('blur', onIdentifierBlur);
      identifierInput.addEventListener('input', onIdentifierInput);
      if (identifierLabel) identifierLabel.addEventListener('click', () => activeElement = "identifier");

      if (passwordInput) {
        passwordInput.addEventListener('focus', onPasswordFocus);
        passwordInput.addEventListener('blur', onPasswordBlur);
      }

      if (showPasswordCheck) {
        showPasswordCheck.addEventListener('change', onPasswordToggleChange);
        showPasswordCheck.addEventListener('focus', onPasswordToggleFocus);
        showPasswordCheck.addEventListener('blur', onPasswordToggleBlur);
        showPasswordCheck.addEventListener('click', onPasswordToggleClick);
      }

      if (showPasswordToggle) {
        showPasswordToggle.addEventListener('mouseup', onPasswordToggleMouseUp);
        showPasswordToggle.addEventListener('mousedown', onPasswordToggleMouseDown);
      }

      TweenMax.set(armL, { x: -93, y: 220, rotation: 105, transformOrigin: "top left", visibility: "hidden" });
      TweenMax.set(armR, { x: -93, y: 220, rotation: -105, transformOrigin: "top right", visibility: "hidden" });
      TweenMax.set(mouth, { transformOrigin: "center center" });
      startBlinking(5);
      inputScrollMax = identifierInput.scrollWidth;

      console.clear();
    }

    initLoginForm();
  });
})();
