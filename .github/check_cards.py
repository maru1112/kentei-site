#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""検定一覧 kentei.html のカードが決まりどおりか検査する。NGならデプロイを止める（2026-10-31 オーナー指示）。

  ・画像は全検定 1280x800（16:10）。カードごとの地色・表示サイズの指定をしない
  ・ロゴを必ず入れる: <img class="exam__logo exam__logo--tl|br" ...> で重ねるか、
    画像に元から入っているなら .exam__img に data-logo="in-image" を付ける
  ・カードは左右交互。exam--rev は本文→画像の順で、本文に exam__body--paper を付ける
  ・画像のURLに ?v= を付ける（キャッシュが7日残るため。差し替えたら値を変える）
"""
import os
import re
import sys
from PIL import Image

os.chdir(os.path.join(os.path.dirname(os.path.abspath(__file__)), ".."))
html = open("kentei.html", encoding="utf-8").read()
cards = re.findall(r'<section class="(exam(?: exam--rev)?)">(.*?)</section>', html, re.S)
ng = []
if not cards:
    ng.append("カードが見つかりません")
prev = None
for cls, body in cards:
    m = re.search(r'<h2 class="exam__ttl">(.*?)</h2>', body)
    name = m.group(1) if m else "（検定名なし）"
    rev = "exam--rev" in cls
    im = re.search(r'<div class="exam__img"([^>]*)>(.*?)</div>', body, re.S)
    if not im:
        ng.append(name + ": 画像の枠がありません")
        continue
    attrs, inner = im.group(1), im.group(2)
    if re.search(r"background-(color|size)", attrs):
        ng.append(name + ": 地色や表示サイズを個別に指定しています")
    u = re.search(r"url\('(assets/[^'?]+)(\?v=[^']+)?'\)", attrs)
    if not u:
        ng.append(name + ": 画像の指定がありません")
    else:
        if not u.group(2):
            ng.append(name + ": 画像のURLに ?v= がありません")
        if not os.path.exists(u.group(1)):
            ng.append(name + ": 画像がありません " + u.group(1))
        elif Image.open(u.group(1)).size != (1280, 800):
            ng.append(name + ": 画像が1280x800ではありません %s" % (Image.open(u.group(1)).size,))
    logo = re.search(r'<img class="exam__logo exam__logo--(?:tl|br)" src="([^"]+)" alt="[^"]+"', inner)
    if logo:
        if not os.path.exists(logo.group(1)):
            ng.append(name + ": ロゴの画像がありません " + logo.group(1))
    elif 'data-logo="in-image"' not in attrs:
        ng.append(name + ": ロゴがありません")
    img_first = body.index("exam__img") < body.index("exam__body")
    if rev == img_first:
        ng.append(name + ": 画像と本文の順番が左右の向きと合っていません")
    if rev and "exam__body--paper" not in body:
        ng.append(name + ": exam--rev の本文に exam__body--paper がありません")
    if prev is not None and rev == prev:
        ng.append(name + ": 前のカードと同じ向きです（左右交互にする）")
    prev = rev
for n in ng:
    print("[NG]", n)
print("カード %d枚: %s" % (len(cards), "NG %d件" % len(ng) if ng else "OK"))
sys.exit(1 if ng else 0)
