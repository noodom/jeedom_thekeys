import logging
import sys
import time
import json
from thekeys import *

gateway = Gateway(sys.argv[1])

if sys.argv[2] == 'open':
	gateway.open(sys.argv[3],sys.argv[4])
elif sys.argv[2] == 'close':
	gateway.close(sys.argv[3],sys.argv[4])
