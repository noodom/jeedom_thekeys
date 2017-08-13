#!/usr/bin/python3

import requests
import json
import time
import sys
import logging
import hashlib
import base64
import hmac

try:
    import http.client as http_client
except ImportError:
    # Python 2
    import httplib as http_client
http_client.HTTPConnection.debuglevel = 1

DEFAULT_HOST = "192.168.0.101"
debug = False

class Gateway:

    def __init__(self):
        if debug:
            # You must initialize logging, otherwise you'll not see debug output.
            logging.basicConfig()
            logging.getLogger().setLevel(logging.DEBUG)
            requests_log = logging.getLogger("requests.packages.urllib3")
            requests_log.setLevel(logging.DEBUG)
            requests_log.propagate = True
        self.host = DEFAULT_HOST

    def set_host(self, host):
        self.host = host

    def open(self, identifier, code):
        self.action("open", identifier, code)

    def close(self, identifier, code):
        self.action("close", identifier, code)

    def action(self, type, identifier, code):
        start = time.time()
        ts = str(int(time.time()))
        hm = hmac.new(code, ts.encode("ascii"), "sha256")
        hash = hm.digest()
        if debug:
            print("hash: " + hm.hexdigest())
        hash = base64.b64encode(hash)

        r = requests.post("http://%s/%s"%(self.host, type), data={"hash": hash, "identifier":identifier, "ts":ts})
        print(r.text)
        resp = json.loads(r.text)
        print("result: %s. Code: %d"%(resp["status"], resp["code"]))
        print("duration: %f"%(time.time() - start))

    def search(self):
        r = requests.get("http://%s/lockers"%self.host)
        resp = json.loads(r.text)
        for d in resp["devices"]:
            print("Found locker %s. RSSI: %d, battery: %d"%(d["identifier"], d["rssi"], d["battery"]))

    def synchronize(self):
        r = requests.get("http://%s/synchronize"%self.host)
        print(r.text)
        resp = json.loads(r.text)
        return resp

    def update(self):
        r = requests.post("http://%s/update"%self.host)
        print(r.text)
        resp = json.loads(r.text)
        return resp

    def synchronize_locker(self, identifier):
        r = requests.post("http://%s/locker/synchronize"%self.host, data={"identifier": identifier})
        print(r.text)

    def update_locker(self, identifier):
        r = requests.post("http://%s/locker/update"%self.host, data={"identifier": identifier})
        print(r.text)
        resp = json.loads(r.text)
        return resp
