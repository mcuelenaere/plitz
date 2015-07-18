<?php
namespace Plitz\Lexer;

class Tokens
{
    const T_RAW = "T_RAW";
    const T_BLOCK_BEGIN = "T_BLOCK_BEGIN";
    const T_BLOCK_END = "T_BLOCK_END";
    const T_BLOCK_IF = "T_BLOCK_IF";
    const T_BLOCK_ELSE = "T_BLOCK_ELSE";
    const T_BLOCK_ELSE_IF = "T_BLOCK_ELSE_IF";
    const T_BLOCK_UNLESS = "T_BLOCK_UNLESS";

    const T_LITERAL = "T_LITERAL";
    const T_STRING = "T_STRING";
    const T_NUMBER = "T_NUMBER";
    const T_BOOL = "T_BOOL";

    const T_OPEN_PAREN = "T_OPEN_PAREN";
    const T_CLOSE_PAREN = "T_CLOSE_PAREN";
    const T_COMMA = "T_COMMA";
    const T_ATTR_SEP = "T_ATTR_SEP";
    const T_PIPE = "T_PIPE";

    const T_EQ = "T_EQ";
    const T_NE = "T_NE";
    const T_GT = "T_GT";
    const T_LT = "T_LT";
    const T_GE = "T_GE";
    const T_LE = "T_LE";

    const T_NOT = "T_NOT";
    const T_AND = "T_AND";
    const T_OR = "T_OR";

    const T_PLUS = "T_PLUS";
    const T_MINUS = "T_MINUS";
    const T_MUL = "T_MUL";
    const T_DIV = "T_DIV";
    const T_MOD = "T_MOD";
}
